<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalLine;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Chart of accounts + account ledger (statement). */
class AccountController extends Controller
{
    public function __construct(private LedgerService $ledger) {}

    /** Flat list ordered by code with period balances; the UI builds the tree from parent_id. */
    public function index(Request $request): JsonResponse
    {
        $bal = $this->ledger->balances($request->query('from'), $request->query('to'));
        $rows = Account::orderBy('code')->get()->map(function (Account $a) use ($bal) {
            $b = $bal[$a->id] ?? null;

            return $a->toArray() + ['debit' => $b['debit'] ?? 0, 'credit' => $b['credit'] ?? 0, 'balance' => $b['balance'] ?? 0];
        });
        // Roll child balances up into groups.
        $byId = $rows->keyBy('id')->all();
        foreach (array_reverse($rows->all()) as $r) {
            if ($r['parent_id'] && isset($byId[$r['parent_id']])) {
                $byId[$r['parent_id']]['balance'] = round($byId[$r['parent_id']]['balance'] + $byId[$r['id']]['balance'], 3);
                $byId[$r['parent_id']]['debit'] += $byId[$r['id']]['debit'];
                $byId[$r['parent_id']]['credit'] += $byId[$r['id']]['credit'];
            }
        }

        return response()->json(array_values($byId));
    }

    /** Account statement with running balance. */
    public function show(Request $request, int $id): JsonResponse
    {
        $a = Account::findOrFail($id);
        $from = $request->query('from'); $to = $request->query('to');
        $q = JournalLine::with('entry:id,number,date,description,reference,source_type,source_id')
            ->where('account_id', $a->id)
            ->whereHas('entry', fn ($e) => $e->where('status', 'posted')->when($from, fn ($x) => $x->whereDate('date', '>=', $from))->when($to, fn ($x) => $x->whereDate('date', '<=', $to)))
            ->get()->sortBy([['entry.date', 'asc'], ['id', 'asc']])->values();

        $openingQ = JournalLine::where('account_id', $a->id)->whereHas('entry', fn ($e) => $e->where('status', 'posted')->when($from, fn ($x) => $x->whereDate('date', '<', $from)));
        $openDr = $from ? (float) (clone $openingQ)->sum('debit') : 0;
        $openCr = $from ? (float) (clone $openingQ)->sum('credit') : 0;
        $sign = $a->isDebitNormal() ? 1 : -1;
        $running = round((float) $a->opening_balance + $sign * ($openDr - $openCr), 3);
        $opening = $running;
        $lines = $q->map(function (JournalLine $l) use (&$running, $sign) {
            $running = round($running + $sign * ((float) $l->debit - (float) $l->credit), 3);

            return ['id' => $l->id, 'date' => $l->entry->date->toDateString(), 'entry_id' => $l->entry->id, 'number' => $l->entry->number, 'description' => $l->description ?: $l->entry->description, 'reference' => $l->entry->reference, 'source_type' => $l->entry->source_type, 'debit' => (float) $l->debit, 'credit' => (float) $l->credit, 'balance' => $running];
        });

        return response()->json(['account' => $a->load('parent:id,code,name'), 'opening' => $opening, 'lines' => $lines, 'closing' => $running, 'totals' => ['debit' => (float) $q->sum('debit'), 'credit' => (float) $q->sum('credit')]]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ledger->ensureChart();
        $data = $this->validated($request);
        $a = Account::create($data);

        return response()->json($a, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $a = Account::findOrFail($id);
        $data = $this->validated($request, $a);
        if ($a->system_key) {   // system accounts keep their type & key
            unset($data['type'], $data['system_key']);
        }
        $a->update($data);

        return response()->json($a->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $a = Account::findOrFail($id);
        if ($a->system_key || $a->lines()->exists() || $a->children()->exists()) {
            $a->update(['is_active' => false]);

            return response()->json(['ok' => true, 'deactivated' => true, 'message' => 'الحساب نظامي أو عليه حركات — تم تعطيله بدل حذفه.']);
        }
        $a->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, ?Account $a = null): array
    {
        $tenantId = app('currentTenantId');

        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('accounts', 'code')->where('tenant_id', $tenantId)->ignore($a?->id)],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(Account::TYPES)],
            'parent_id' => ['nullable', Rule::exists('accounts', 'id')->where('tenant_id', $tenantId)],
            'is_group' => ['boolean'],
            'opening_balance' => ['nullable', 'numeric'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
