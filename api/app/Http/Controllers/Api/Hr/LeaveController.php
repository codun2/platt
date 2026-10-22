<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Leave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Leave::with('employee:id,name,code,department', 'approver:id,name')->orderByDesc('from')->orderByDesc('id');
        if ($st = $request->query('status')) {
            $q->where('status', $st);
        }
        if ($e = $request->query('employee_id')) {
            $q->where('employee_id', $e);
        }
        if ($t = $request->query('type')) {
            $q->where('type', $t);
        }
        $stats = ['pending' => Leave::where('status', 'pending')->count(), 'approved_month' => Leave::where('status', 'approved')->where('from', 'like', now()->format('Y-m').'%')->count(),
            'on_leave_today' => Leave::where('status', 'approved')->whereDate('from', '<=', now())->whereDate('to', '>=', now())->count()];

        return response()->json(['stats' => $stats] + $q->paginate(30)->toArray());
    }

    public function show(int $id): JsonResponse
    {
        $l = Leave::with('employee', 'approver:id,name')->findOrFail($id);
        $used = (int) Leave::where('employee_id', $l->employee_id)->where('status', 'approved')->where('type', 'annual')->whereYear('from', $l->from->year)->where('id', '!=', $l->id)->sum('days');

        return response()->json(['leave' => $l, 'balance' => ['annual' => $l->employee->annual_leave_days, 'used' => $used, 'remaining' => max(0, $l->employee->annual_leave_days - $used)]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $l = Leave::create($data + ['status' => 'pending']);

        return response()->json($l->load('employee:id,name'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $l = Leave::findOrFail($id);
        if ($l->status !== 'pending') {
            return response()->json(['message' => 'لا يمكن تعديل إجازة تمت الموافقة عليها أو رُفضت.'], 422);
        }
        $l->update($this->validated($request));

        return response()->json($l->fresh()->load('employee:id,name'));
    }

    /** approve | reject | pending (reopen). Approving marks attendance as "leave" across the range. */
    public function setStatus(Request $request, int $id): JsonResponse
    {
        $l = Leave::findOrFail($id);
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected', 'pending'])], 'note' => ['nullable', 'string']]);
        DB::transaction(function () use ($l, $data, $request) {
            $l->update(['status' => $data['status'], 'note' => $data['note'] ?? $l->note, 'approved_by' => $data['status'] === 'pending' ? null : $request->user()->id, 'approved_at' => $data['status'] === 'pending' ? null : now()]);
            if ($data['status'] === 'approved') {
                for ($d = $l->from->copy(); $d->lte($l->to); $d->addDay()) {
                    Attendance::updateOrCreate(['employee_id' => $l->employee_id, 'date' => $d->toDateString()], ['status' => 'leave', 'note' => 'إجازة '.$l->type, 'user_id' => $request->user()->id, 'tenant_id' => app('currentTenantId')]);
                }
            } else {
                Attendance::where('employee_id', $l->employee_id)->whereBetween('date', [$l->from->toDateString(), $l->to->toDateString()])->where('status', 'leave')->delete();
            }
        });

        return response()->json($l->fresh()->load('employee:id,name', 'approver:id,name'));
    }

    public function destroy(int $id): JsonResponse
    {
        $l = Leave::findOrFail($id);
        if ($l->status === 'approved') {
            return response()->json(['message' => 'ألغِ الموافقة أولاً قبل الحذف.'], 422);
        }
        $l->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request): array
    {
        $tenantId = app('currentTenantId');
        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'type' => ['required', Rule::in(Leave::TYPES)],
            'from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'],
            'reason' => ['nullable', 'string', 'max:255'], 'note' => ['nullable', 'string'],
        ]);
        $data['days'] = Carbon::parse($data['from'])->diffInDays(Carbon::parse($data['to'])) + 1;

        return $data;
    }
}
