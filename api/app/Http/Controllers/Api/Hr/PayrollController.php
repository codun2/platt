<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Sequence;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Monthly payroll: generate from employees + attendance, adjust, approve, pay (posts to the ledger). */
class PayrollController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = Payroll::withCount('items')->with('creator:id,name')->orderByDesc('period')->get();
        $stats = ['count' => $rows->count(), 'paid_total' => (float) $rows->where('status', 'paid')->sum('total_net'), 'last' => $rows->first()?->period,
            'employees' => Employee::where('status', '!=', 'terminated')->count(),
            'expected' => (float) Employee::where('status', '!=', 'terminated')->selectRaw('coalesce(sum(basic_salary + housing_allowance + transport_allowance + other_allowance - fixed_deductions),0) as t')->value('t')];

        return response()->json(['stats' => $stats, 'data' => $rows, 'money' => Account::where('type', 'asset')->whereIn('system_key', ['cash', 'bank'])->get(['id', 'code', 'name', 'system_key'])]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(Payroll::with('items.employee:id,code,name,department,job_title,bank_name,iban', 'creator:id,name', 'paidFrom:id,code,name')->findOrFail($id));
    }

    /** Create a draft payroll for YYYY-MM: one item per non-terminated employee; absences deducted at daily rate (gross/30). */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
        if (Payroll::where('period', $data['period'])->exists()) {
            return response()->json(['message' => 'يوجد كشف رواتب لهذا الشهر مسبقاً.'], 422);
        }
        $p = DB::transaction(function () use ($data, $request) {
            $p = Payroll::create(['number' => Sequence::next('payroll', 'PAY'), 'period' => $data['period'], 'status' => 'draft', 'created_by' => $request->user()->id]);
            foreach (Employee::where('status', '!=', 'terminated')->get() as $e) {
                $absent = (float) Attendance::where('employee_id', $e->id)->where('date', 'like', $data['period'].'%')->where('status', 'absent')->count()
                    + 0.5 * Attendance::where('employee_id', $e->id)->where('date', 'like', $data['period'].'%')->where('status', 'half_day')->count();
                $unpaid = (int) Leave::where('employee_id', $e->id)->where('status', 'approved')->where('type', 'unpaid')->where('from', 'like', $data['period'].'%')->sum('days');
                $days = $absent + $unpaid;
                $daily = round($e->gross_salary / 30, 3);
                $item = new PayrollItem(['payroll_id' => $p->id, 'employee_id' => $e->id, 'basic' => $e->basic_salary, 'allowances' => $e->allowancesTotal(),
                    'absence_days' => $days, 'absence_deduction' => round($daily * $days, 3), 'fixed_deductions' => $e->fixed_deductions]);
                $item->net = $item->computeNet();
                $item->save();
            }
            $p->recalc();

            return $p;
        });

        return response()->json($p->fresh()->load('items.employee:id,name'), 201);
    }

    /** Edit the adjustable columns of one line (draft only) and recompute. */
    public function updateItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $p = Payroll::findOrFail($id);
        if ($p->status !== 'draft') {
            return response()->json(['message' => 'كشف الرواتب معتمد ولا يُعدَّل.'], 422);
        }
        $item = PayrollItem::where('payroll_id', $p->id)->findOrFail($itemId);
        $data = $request->validate(['overtime' => 'nullable|numeric|min:0', 'bonus' => 'nullable|numeric|min:0', 'absence_days' => 'nullable|numeric|min:0', 'absence_deduction' => 'nullable|numeric|min:0', 'other_deductions' => 'nullable|numeric|min:0', 'advance' => 'nullable|numeric|min:0', 'note' => 'nullable|string|max:255']);
        $item->fill($data);
        $item->net = $item->computeNet();
        $item->save();
        $p->recalc();

        return response()->json($p->fresh()->load('items.employee:id,code,name,department,job_title,bank_name,iban'));
    }

    public function approve(int $id): JsonResponse
    {
        $p = Payroll::findOrFail($id);
        if ($p->status !== 'draft') {
            return response()->json(['message' => 'الكشف ليس مسودّة.'], 422);
        }
        $p->update(['status' => 'approved', 'approved_at' => now()]);

        return response()->json($p->fresh());
    }

    /** Mark paid and post: Dr salaries expense (gross + additions) / Cr money (net) / Cr salaries payable... simplified to Dr salaries (net) Cr money (net). */
    public function pay(Request $request, int $id, LedgerService $ledger): JsonResponse
    {
        $p = Payroll::findOrFail($id);
        if ($p->status !== 'approved') {
            return response()->json(['message' => 'اعتمد الكشف أولاً.'], 422);
        }
        $data = $request->validate(['paid_from_account_id' => ['required', Rule::exists('accounts', 'id')->where('tenant_id', app('currentTenantId'))->where('type', 'asset')], 'date' => ['nullable', 'date']]);
        DB::transaction(function () use ($p, $data, $ledger) {
            $e = $ledger->post([
                ['account' => 'salaries', 'debit' => (float) $p->total_net, 'description' => 'رواتب '.$p->period],
                ['account' => $data['paid_from_account_id'], 'credit' => (float) $p->total_net],
            ], ['date' => $data['date'] ?? now()->toDateString(), 'description' => 'صرف رواتب شهر '.$p->period, 'reference' => $p->number, 'source_type' => 'payroll', 'source_id' => $p->id]);
            $p->update(['status' => 'paid', 'paid_at' => now(), 'paid_from_account_id' => $data['paid_from_account_id'], 'journal_entry_id' => $e->id]);
        });

        return response()->json($p->fresh()->load('paidFrom:id,code,name'));
    }

    public function destroy(int $id): JsonResponse
    {
        $p = Payroll::findOrFail($id);
        if ($p->status === 'paid') {
            return response()->json(['message' => 'كشف مدفوع لا يُحذف.'], 422);
        }
        $p->delete();

        return response()->json(['ok' => true]);
    }
}
