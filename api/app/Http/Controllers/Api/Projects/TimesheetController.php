<?php

namespace App\Http\Controllers\Api\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Timesheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Time entries; billable hours are later billed via ProjectController@bill. */
class TimesheetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $q = Timesheet::with('project:id,number,title,kind', 'user:id,name', 'invoice:id,number')->whereBetween('date', [$from, $to]);
        foreach (['project_id', 'user_id'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }
        if ($request->query('billing') === 'unbilled') {
            $q->where('billable', true)->whereNull('invoice_id');
        } elseif ($request->query('billing') === 'billed') {
            $q->whereNotNull('invoice_id');
        }
        $all = (clone $q)->get();

        return response()->json([
            'page' => $q->latest('date')->latest('id')->paginate(50), 'from' => $from, 'to' => $to,
            'totals' => [
                'hours' => round((float) $all->sum('hours'), 2),
                'billable_hours' => round((float) $all->where('billable', true)->sum('hours'), 2),
                'amount' => round((float) $all->sum('amount'), 3),
                'unbilled' => round((float) $all->where('billable', true)->whereNull('invoice_id')->sum('amount'), 3),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['user_id'] = $data['user_id'] ?? $request->user()->id;

        return response()->json(Timesheet::create($data), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $t = Timesheet::findOrFail($id);
        abort_if($t->invoice_id, 422, 'هذه الساعات مفوترة ولا يمكن تعديلها.');
        $t->update($this->validated($request));

        return response()->json($t->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $t = Timesheet::findOrFail($id);
        abort_if($t->invoice_id, 422, 'هذه الساعات مفوترة ولا يمكن حذفها.');
        $t->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request): array
    {
        $tn = app('currentTenantId');
        $data = $request->validate([
            'project_id' => ['required', Rule::exists('projects', 'id')->where('tenant_id', $tn)],
            'task_id' => ['nullable', Rule::exists('project_tasks', 'id')->where('tenant_id', $tn)],
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tn)],
            'date' => 'required|date', 'hours' => 'required|numeric|gt:0|max:24', 'description' => 'nullable|string|max:255',
            'billable' => 'boolean', 'rate' => 'nullable|numeric|min:0',
        ]);
        $data['billable'] = $data['billable'] ?? true;
        // Rate defaults to the project's hourly rate.
        $data['rate'] = $data['rate'] ?? (float) Project::whereKey($data['project_id'])->value('hourly_rate');
        $data['amount'] = $data['billable'] ? round((float) $data['hours'] * (float) $data['rate'], 3) : 0;

        return $data;
    }
}
