<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Employee::with('user:id,name,email')->orderBy('name');
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")->orWhere('job_title', 'like', "%$s%")->orWhere('national_id', 'like', "%$s%"));
        }
        if ($d = $request->query('department')) {
            $q->where('department', $d);
        }
        if ($st = $request->query('status')) {
            $q->where('status', $st);
        }
        $stats = [
            'total' => Employee::count(), 'active' => Employee::where('status', 'active')->count(),
            'on_leave' => Employee::where('status', 'on_leave')->count(),
            'monthly_payroll' => (float) Employee::where('status', '!=', 'terminated')->selectRaw('coalesce(sum(basic_salary + housing_allowance + transport_allowance + other_allowance - fixed_deductions),0) as t')->value('t'),
            'departments' => Employee::whereNotNull('department')->where('department', '!=', '')->distinct()->orderBy('department')->pluck('department'),
        ];

        return response()->json(['stats' => $stats] + ($request->boolean('all') ? ['data' => $q->get()] : $q->paginate(50)->toArray()));
    }

    public function show(int $id): JsonResponse
    {
        $e = Employee::with('user:id,name,email')->findOrFail($id);
        $month = now()->format('Y-m');
        $att = Attendance::where('employee_id', $e->id)->where('date', 'like', "$month%")->get();
        $year = now()->year;
        $usedLeave = (int) $e->leaves()->where('status', 'approved')->where('type', 'annual')->whereYear('from', $year)->sum('days');

        return response()->json([
            'employee' => $e,
            'attendance_month' => [
                'present' => $att->whereIn('status', ['present', 'late', 'half_day'])->count(), 'absent' => $att->where('status', 'absent')->count(),
                'late' => $att->where('status', 'late')->count(), 'leave' => $att->where('status', 'leave')->count(),
                'late_minutes' => (int) $att->sum('late_minutes'), 'overtime_minutes' => (int) $att->sum('overtime_minutes'),
            ],
            'leave_balance' => ['annual' => $e->annual_leave_days, 'used' => $usedLeave, 'remaining' => max(0, $e->annual_leave_days - $usedLeave)],
            'leaves' => $e->leaves()->orderByDesc('from')->limit(20)->get(),
            'attendance' => $e->attendances()->orderByDesc('date')->limit(31)->get(),
            'payslips' => $e->payrollItems()->with('payroll:id,number,period,status,paid_at')->orderByDesc('id')->limit(12)->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Employee::create($this->validated($request)), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $e = Employee::findOrFail($id);
        $e->update($this->validated($request, $e));

        return response()->json($e->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $e = Employee::findOrFail($id);
        if ($e->payrollItems()->exists() || $e->attendances()->exists()) {
            $e->update(['status' => 'terminated', 'end_date' => $e->end_date ?? now()->toDateString()]);

            return response()->json(['ok' => true, 'deactivated' => true, 'message' => 'الموظف له سجلات — تم إنهاء خدمته بدل الحذف.']);
        }
        $e->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, ?Employee $e = null): array
    {
        $tenantId = app('currentTenantId');

        return $request->validate([
            'code' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'employment_type' => ['nullable', Rule::in(['full_time', 'part_time', 'contract', 'daily'])],
            'hire_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['active', 'on_leave', 'terminated'])],
            'phone' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:150'],
            'national_id' => ['nullable', 'string', 'max:50'], 'nationality' => ['nullable', 'string', 'max:60'],
            'birth_date' => ['nullable', 'date'], 'gender' => ['nullable', Rule::in(['male', 'female'])],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'address' => ['nullable', 'string', 'max:255'], 'bank_name' => ['nullable', 'string', 'max:100'], 'iban' => ['nullable', 'string', 'max:60'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'], 'housing_allowance' => ['nullable', 'numeric', 'min:0'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'], 'other_allowance' => ['nullable', 'numeric', 'min:0'],
            'fixed_deductions' => ['nullable', 'numeric', 'min:0'], 'annual_leave_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'emergency_name' => ['nullable', 'string', 'max:150'], 'emergency_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
