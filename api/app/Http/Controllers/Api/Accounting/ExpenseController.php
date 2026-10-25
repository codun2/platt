<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Sequence;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Expenses: each one posts Dr expense (+ Dr VAT in) / Cr cash|bank automatically. */
class ExpenseController extends Controller
{
    /** Expense accounts the system posts on its own (never entered by hand as an expense). */
    private const AUTO = ['cogs', 'salaries'];

    public function __construct(private LedgerService $ledger) {}

    public function index(Request $request): JsonResponse
    {
        $this->ledger->ensureChart();
        $q = Expense::with('account:id,code,name', 'paidFrom:id,code,name', 'supplier:id,name', 'creator:id,name')->orderByDesc('date')->orderByDesc('id');
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('number', 'like', "%$s%")->orWhere('payee', 'like', "%$s%")->orWhere('description', 'like', "%$s%")->orWhere('reference', 'like', "%$s%"));
        }
        if ($a = $request->query('account_id')) {
            $q->where('account_id', $a);
        }
        if ($from = $request->query('from')) {
            $q->whereDate('date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('date', '<=', $to);
        }
        $month = Expense::whereDate('date', '>=', now()->startOfMonth());
        $stats = [
            'count' => Expense::count(),
            'total' => (float) Expense::sum('total'),
            'month_total' => (float) (clone $month)->sum('total'),
            'month_count' => (clone $month)->count(),
            'by_category' => Expense::query()->join('accounts', 'accounts.id', '=', 'expenses.account_id')
                ->whereDate('expenses.date', '>=', now()->startOfMonth())
                ->groupBy('accounts.id', 'accounts.name')->selectRaw('accounts.id, accounts.name, sum(expenses.total) as total')
                ->orderByDesc('total')->limit(6)->get(),
        ];

        return response()->json(['stats' => $stats] + $q->paginate(30)->toArray());
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(Expense::with('account:id,code,name', 'paidFrom:id,code,name', 'supplier:id,name,phone', 'creator:id,name', 'entry.lines.account:id,code,name')->findOrFail($id));
    }

    /** Accounts for the form: expense categories + money accounts. */
    public function meta(): JsonResponse
    {
        $this->ledger->ensureChart();

        return response()->json([
            // Cost of goods and salaries are posted by the system (POS / invoices / payroll):
            // offering them here would book the same cost twice.
            'categories' => Account::where('type', 'expense')->where('is_group', false)->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('system_key')->orWhereNotIn('system_key', self::AUTO))->orderBy('code')->get(['id', 'code', 'name']),
            'money' => Account::where('type', 'asset')->whereIn('system_key', ['cash', 'bank'])->orWhere(fn ($w) => $w->where('type', 'asset')->where('is_group', false)->where('code', 'like', '1%')->whereNull('system_key'))->orderBy('code')->get(['id', 'code', 'name', 'system_key']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $e = DB::transaction(function () use ($data, $request) {
            $e = Expense::create($data + ['number' => Sequence::next('expense', 'EXP'), 'total' => round((float) $data['amount'] + (float) ($data['tax'] ?? 0), 3), 'created_by' => $request->user()->id]);
            $e->update(['journal_entry_id' => $this->postEntry($e)->id]);

            return $e;
        });

        return response()->json($e->load('account:id,code,name', 'paidFrom:id,code,name'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $e = Expense::findOrFail($id);
        $data = $this->validated($request);
        DB::transaction(function () use ($e, $data) {
            $e->entry?->delete();
            $e->update($data + ['total' => round((float) $data['amount'] + (float) ($data['tax'] ?? 0), 3)]);
            $e->update(['journal_entry_id' => $this->postEntry($e->fresh())->id]);
        });

        return response()->json($e->fresh()->load('account:id,code,name', 'paidFrom:id,code,name'));
    }

    public function destroy(int $id): JsonResponse
    {
        $e = Expense::findOrFail($id);
        DB::transaction(function () use ($e) { $e->entry?->delete(); $e->delete(); });

        return response()->json(['ok' => true]);
    }

    private function postEntry(Expense $e)
    {
        $lines = [['account' => $e->account_id, 'debit' => (float) $e->amount, 'description' => $e->payee ?: $e->description]];
        if ((float) $e->tax > 0) {
            $lines[] = ['account' => 'vat_in', 'debit' => (float) $e->tax];
        }
        $lines[] = ['account' => $e->paid_from_account_id, 'credit' => (float) $e->total];

        return $this->ledger->post($lines, ['date' => $e->date->toDateString(), 'description' => 'مصروف '.$e->number.($e->description ? ' — '.$e->description : ''), 'reference' => $e->reference ?: $e->number, 'source_type' => 'expense', 'source_id' => $e->id]);
    }

    private function validated(Request $request): array
    {
        $tenantId = app('currentTenantId');

        return $request->validate([
            'date' => ['required', 'date'],
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('tenant_id', $tenantId)->where('type', 'expense')->where('is_group', false),
                function ($attr, $value, $fail) {
                    $key = Account::whereKey($value)->value('system_key');
                    if (in_array($key, self::AUTO, true)) {
                        $fail($key === 'salaries' ? 'الرواتب تُسجَّل تلقائياً من شاشة الرواتب — لا تُدخل هنا حتى لا تُحسب مرتين.' : 'تكلفة البضاعة تُسجَّل تلقائياً مع كل بيع — لا تُدخل هنا حتى لا تُحسب مرتين.');
                    }
                }],
            'paid_from_account_id' => ['required', Rule::exists('accounts', 'id')->where('tenant_id', $tenantId)->where('type', 'asset')->where('is_group', false)],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)],
            'payee' => ['nullable', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', Rule::in(Expense::METHODS)],
            'reference' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
