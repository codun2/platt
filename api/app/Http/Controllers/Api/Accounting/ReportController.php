<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Financial reports computed live from posted journal lines. */
class ReportController extends Controller
{
    public function __construct(private LedgerService $ledger) {}

    /** Accounting home: period KPIs + recent entries + expenses by category. */
    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);
        $bal = $this->ledger->balances($from, $to, false);
        $sum = fn (string $type) => round(array_sum(array_map(fn ($b) => $b['account']->is_group ? 0 : $b['balance'], array_filter($bal, fn ($b) => $b['account']->type === $type))), 3);
        $revenue = $sum('revenue'); $expense = $sum('expense');
        $key = fn (string $k) => round(($bal[Account::where('system_key', $k)->value('id')]['balance'] ?? 0), 3);
        $asOf = $this->ledger->balances(null, $to);
        $keyAsOf = fn (string $k) => round(($asOf[Account::where('system_key', $k)->value('id')]['balance'] ?? 0), 3);

        return response()->json([
            'period' => compact('from', 'to'),
            'revenue' => $revenue, 'expense' => $expense, 'profit' => round($revenue - $expense, 3),
            'cash' => $keyAsOf('cash'), 'bank' => $keyAsOf('bank'), 'receivable' => $keyAsOf('ar'), 'payable' => $keyAsOf('ap'), 'vat_due' => round($keyAsOf('vat_out') - $keyAsOf('vat_in'), 3),
            'period_cash_in' => max(0, $key('cash') + $key('bank')),
            'entries_count' => JournalEntry::where('status', 'posted')->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->count(),
            'recent' => JournalEntry::withCount('lines')->orderByDesc('date')->orderByDesc('id')->limit(8)->get(),
            'expenses_by_category' => Expense::query()->join('accounts', 'accounts.id', '=', 'expenses.account_id')
                ->whereDate('expenses.date', '>=', $from)->whereDate('expenses.date', '<=', $to)
                ->groupBy('accounts.id', 'accounts.name')->selectRaw('accounts.id, accounts.name, sum(expenses.total) as total')->orderByDesc('total')->get(),
            'revenue_accounts' => array_values(array_map(fn ($b) => ['id' => $b['account']->id, 'name' => $b['account']->name, 'balance' => $b['balance']], array_filter($bal, fn ($b) => $b['account']->type === 'revenue' && ! $b['account']->is_group && abs($b['balance']) > 0))),
        ]);
    }

    /** ميزان المراجعة */
    public function trialBalance(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);
        $bal = $this->ledger->balances($from, $to, true);
        $rows = []; $td = 0; $tc = 0;
        foreach ($bal as $b) {
            $a = $b['account'];
            if ($a->is_group) { continue; }
            $net = ($a->isDebitNormal() ? 1 : -1) * $b['balance'];   // positive = debit balance
            if (abs($net) < 0.0005 && $b['debit'] == 0 && $b['credit'] == 0) { continue; }
            $dr = $net > 0 ? $net : 0; $cr = $net < 0 ? -$net : 0;
            $td += $dr; $tc += $cr;
            $rows[] = ['id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'type' => $a->type, 'debit_moves' => $b['debit'], 'credit_moves' => $b['credit'], 'debit' => round($dr, 3), 'credit' => round($cr, 3)];
        }

        return response()->json(['period' => compact('from', 'to'), 'rows' => $rows, 'total_debit' => round($td, 3), 'total_credit' => round($tc, 3), 'balanced' => abs($td - $tc) < 0.005]);
    }

    /** قائمة الدخل */
    public function incomeStatement(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);
        $bal = $this->ledger->balances($from, $to, false);
        $pick = fn (string $type) => array_values(array_map(fn ($b) => ['id' => $b['account']->id, 'code' => $b['account']->code, 'name' => $b['account']->name, 'amount' => $b['balance']],
            array_filter($bal, fn ($b) => $b['account']->type === $type && ! $b['account']->is_group && abs($b['balance']) > 0.0005)));
        $rev = $pick('revenue'); $exp = $pick('expense');
        $cogs = array_values(array_filter($exp, fn ($r) => Account::find($r['id'])?->system_key === 'cogs'));
        $opex = array_values(array_filter($exp, fn ($r) => Account::find($r['id'])?->system_key !== 'cogs'));
        $tr = round(array_sum(array_column($rev, 'amount')), 3);
        $tcogs = round(array_sum(array_column($cogs, 'amount')), 3);
        $topex = round(array_sum(array_column($opex, 'amount')), 3);

        return response()->json(['period' => compact('from', 'to'), 'revenue' => $rev, 'total_revenue' => $tr, 'cogs' => $cogs, 'total_cogs' => $tcogs, 'gross_profit' => round($tr - $tcogs, 3), 'expenses' => $opex, 'total_expenses' => $topex, 'net_profit' => round($tr - $tcogs - $topex, 3)]);
    }

    /** المركز المالي (as of date) */
    public function balanceSheet(Request $request): JsonResponse
    {
        $to = $request->query('to', now()->toDateString());
        $bal = $this->ledger->balances(null, $to, true);
        $pick = fn (string $type) => array_values(array_map(fn ($b) => ['id' => $b['account']->id, 'code' => $b['account']->code, 'name' => $b['account']->name, 'amount' => $b['balance']],
            array_filter($bal, fn ($b) => $b['account']->type === $type && ! $b['account']->is_group && abs($b['balance']) > 0.0005)));
        $assets = $pick('asset'); $liab = $pick('liability'); $equity = $pick('equity');
        $rev = round(array_sum(array_map(fn ($b) => $b['account']->is_group ? 0 : $b['balance'], array_filter($bal, fn ($b) => $b['account']->type === 'revenue'))), 3);
        $exp = round(array_sum(array_map(fn ($b) => $b['account']->is_group ? 0 : $b['balance'], array_filter($bal, fn ($b) => $b['account']->type === 'expense'))), 3);
        $profit = round($rev - $exp, 3);
        $ta = round(array_sum(array_column($assets, 'amount')), 3);
        $tl = round(array_sum(array_column($liab, 'amount')), 3);
        $te = round(array_sum(array_column($equity, 'amount')) + $profit, 3);

        return response()->json(['as_of' => $to, 'assets' => $assets, 'total_assets' => $ta, 'liabilities' => $liab, 'total_liabilities' => $tl, 'equity' => $equity, 'current_profit' => $profit, 'total_equity' => $te, 'balanced' => abs($ta - ($tl + $te)) < 0.005]);
    }

    private function period(Request $request): array
    {
        return [$request->query('from', now()->startOfMonth()->toDateString()), $request->query('to', now()->toDateString())];
    }
}
