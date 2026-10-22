<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Journal entries: automatic ones are read-only; manual ones are editable. */
class JournalController extends Controller
{
    public function __construct(private LedgerService $ledger) {}

    public function index(Request $request): JsonResponse
    {
        $this->ledger->ensureChart();
        $q = JournalEntry::with('creator:id,name')->withCount('lines')->orderByDesc('date')->orderByDesc('id');
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('number', 'like', "%$s%")->orWhere('description', 'like', "%$s%")->orWhere('reference', 'like', "%$s%"));
        }
        if ($st = $request->query('source_type')) {
            $st === 'manual' ? $q->where(fn ($w) => $w->whereNull('source_type')->orWhere('source_type', 'manual')) : $q->where('source_type', $st);
        }
        if ($from = $request->query('from')) {
            $q->whereDate('date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('date', '<=', $to);
        }
        $stats = [
            'count' => JournalEntry::count(),
            'month_count' => JournalEntry::whereDate('date', '>=', now()->startOfMonth())->count(),
            'month_total' => (float) JournalEntry::where('status', 'posted')->whereDate('date', '>=', now()->startOfMonth())->sum('total'),
            'manual_count' => JournalEntry::where(fn ($w) => $w->whereNull('source_type')->orWhere('source_type', 'manual'))->count(),
        ];

        return response()->json(['stats' => $stats] + $q->paginate(30)->toArray());
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(JournalEntry::with('lines.account:id,code,name,type', 'creator:id,name')->findOrFail($id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        try {
            $e = $this->ledger->post($data['lines'], ['date' => $data['date'], 'description' => $data['description'] ?? null, 'reference' => $data['reference'] ?? null, 'source_type' => 'manual', 'status' => $data['status'] ?? 'posted', 'user_id' => $request->user()->id]);
        } catch (\InvalidArgumentException $ex) {
            return response()->json(['message' => $ex->getMessage()], 422);
        }

        return response()->json($e->load('lines.account:id,code,name'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $e = JournalEntry::findOrFail($id);
        if (! $e->isManual()) {
            return response()->json(['message' => 'هذا القيد آلي (من فاتورة/مصروف/حجز) ولا يُعدَّل يدوياً.'], 422);
        }
        $data = $this->validated($request);
        $dr = 0; $cr = 0;
        foreach ($data['lines'] as $l) { $dr += (float) ($l['debit'] ?? 0); $cr += (float) ($l['credit'] ?? 0); }
        if (abs($dr - $cr) > 0.0005) {
            return response()->json(['message' => 'القيد غير متوازن: مدين '.number_format($dr, 3).' ≠ دائن '.number_format($cr, 3)], 422);
        }
        DB::transaction(function () use ($e, $data, $dr) {
            $e->update(['date' => $data['date'], 'description' => $data['description'] ?? null, 'reference' => $data['reference'] ?? null, 'status' => $data['status'] ?? $e->status, 'total' => round($dr, 3), 'posted_at' => ($data['status'] ?? $e->status) === 'posted' ? ($e->posted_at ?: now()) : null]);
            $e->lines()->delete();
            foreach (array_values($data['lines']) as $i => $l) {
                if ((float) ($l['debit'] ?? 0) <= 0 && (float) ($l['credit'] ?? 0) <= 0) { continue; }
                JournalLine::create(['journal_entry_id' => $e->id, 'account_id' => $l['account_id'], 'debit' => $l['debit'] ?? 0, 'credit' => $l['credit'] ?? 0, 'description' => $l['description'] ?? null, 'sort' => $i]);
            }
        });

        return response()->json($e->fresh()->load('lines.account:id,code,name'));
    }

    public function destroy(int $id): JsonResponse
    {
        $e = JournalEntry::findOrFail($id);
        if (! $e->isManual()) {
            return response()->json(['message' => 'القيود الآلية لا تُحذف — ألغِ المستند المصدر.'], 422);
        }
        $e->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request): array
    {
        $tenantId = app('currentTenantId');
        $data = $request->validate([
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(['draft', 'posted'])],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', Rule::exists('accounts', 'id')->where('tenant_id', $tenantId)->where('is_group', false)],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ]);
        $data['lines'] = array_map(fn ($l) => ['account' => $l['account_id'], 'account_id' => $l['account_id'], 'debit' => $l['debit'] ?? 0, 'credit' => $l['credit'] ?? 0, 'description' => $l['description'] ?? null], $data['lines']);

        return $data;
    }
}
