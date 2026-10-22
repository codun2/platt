<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Daily attendance sheet + monthly summary. */
class AttendanceController extends Controller
{
    /** Sheet for one day: every non-terminated employee with their record (or null). */
    public function day(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());
        $emps = Employee::where('status', '!=', 'terminated')->orderBy('name')->get(['id', 'code', 'name', 'department', 'job_title']);
        $rows = Attendance::whereDate('date', $date)->get()->keyBy('employee_id');

        return response()->json([
            'date' => $date,
            'rows' => $emps->map(fn ($e) => ['employee' => $e, 'attendance' => $rows[$e->id] ?? null]),
            'summary' => ['present' => $rows->whereIn('status', ['present', 'late', 'half_day'])->count(), 'absent' => $rows->where('status', 'absent')->count(), 'leave' => $rows->where('status', 'leave')->count(), 'unmarked' => $emps->count() - $rows->count()],
        ]);
    }

    /** Upsert many records for a day at once. */
    public function bulk(Request $request): JsonResponse
    {
        $tenantId = app('currentTenantId');
        $data = $request->validate([
            'date' => ['required', 'date'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.employee_id' => ['required', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'rows.*.status' => ['required', Rule::in(Attendance::STATUSES)],
            'rows.*.check_in' => ['nullable', 'date_format:H:i'],
            'rows.*.check_out' => ['nullable', 'date_format:H:i'],
            'rows.*.late_minutes' => ['nullable', 'integer', 'min:0'],
            'rows.*.overtime_minutes' => ['nullable', 'integer', 'min:0'],
            'rows.*.note' => ['nullable', 'string', 'max:255'],
        ]);
        DB::transaction(function () use ($data, $request) {
            foreach ($data['rows'] as $r) {
                Attendance::updateOrCreate(
                    ['employee_id' => $r['employee_id'], 'date' => $data['date']],
                    ['status' => $r['status'], 'check_in' => $r['check_in'] ?? null, 'check_out' => $r['check_out'] ?? null, 'late_minutes' => $r['late_minutes'] ?? 0, 'overtime_minutes' => $r['overtime_minutes'] ?? 0, 'note' => $r['note'] ?? null, 'user_id' => $request->user()->id, 'tenant_id' => app('currentTenantId')],
                );
            }
        });

        return response()->json(['ok' => true, 'count' => count($data['rows'])]);
    }

    /** Per-employee counts for a month (YYYY-MM). */
    public function summary(Request $request): JsonResponse
    {
        $month = $request->query('month', now()->format('Y-m'));
        $emps = Employee::where('status', '!=', 'terminated')->orderBy('name')->get(['id', 'code', 'name', 'department']);
        $rows = Attendance::where('date', 'like', "$month%")->get()->groupBy('employee_id');

        return response()->json([
            'month' => $month,
            'rows' => $emps->map(function ($e) use ($rows) {
                $a = $rows[$e->id] ?? collect();

                return ['employee' => $e, 'present' => $a->whereIn('status', ['present', 'late'])->count(), 'half_day' => $a->where('status', 'half_day')->count(), 'absent' => $a->where('status', 'absent')->count(), 'late' => $a->where('status', 'late')->count(), 'leave' => $a->where('status', 'leave')->count(), 'late_minutes' => (int) $a->sum('late_minutes'), 'overtime_minutes' => (int) $a->sum('overtime_minutes'), 'days_marked' => $a->count()];
            }),
        ]);
    }
}
