<?php

namespace App\Http\Controllers\Api\Projects;

use App\Http\Controllers\Controller;
use App\Models\ProjectTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Tasks and court hearings (agenda). */
class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $now = now();
        $q = ProjectTask::with('project:id,number,title,kind,court', 'assignee:id,name');
        match ($request->query('scope', 'upcoming')) {
            'upcoming' => $q->whereIn('status', ['todo', 'doing'])->where(fn ($w) => $w->whereNull('due_at')->orWhere('due_at', '>=', $now->copy()->startOfDay()))->orderByRaw('due_at is null')->orderBy('due_at'),
            'today' => $q->whereIn('status', ['todo', 'doing'])->whereBetween('due_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])->orderBy('due_at'),
            'week' => $q->whereIn('status', ['todo', 'doing'])->whereBetween('due_at', [$now->copy()->startOfDay(), $now->copy()->addDays(7)->endOfDay()])->orderBy('due_at'),
            'overdue' => $q->whereIn('status', ['todo', 'doing'])->where('due_at', '<', $now)->orderBy('due_at'),
            'done' => $q->whereIn('status', ['done', 'postponed', 'cancelled'])->latest('done_at')->latest('id'),
            default => $q->latest('due_at'),
        };
        foreach (['kind', 'assigned_to', 'project_id'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }
        if ($request->boolean('mine')) {
            $q->where('assigned_to', $request->user()->id);
        }

        return response()->json($q->paginate(40));
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(ProjectTask::create($this->validated($request)), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $t = ProjectTask::findOrFail($id);
        $data = $this->validated($request, $t);
        $data['done_at'] = $data['status'] === 'done' ? ($t->done_at ?? now()) : null;
        $t->update($data);

        return response()->json($t->fresh(['assignee:id,name']));
    }

    /**
     * Close a task / hearing: done (with outcome) or postponed. For a hearing,
     * `next_at` schedules the next hearing in the same case.
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $t = ProjectTask::findOrFail($id);
        $d = $request->validate(['status' => 'required|in:done,postponed,cancelled', 'outcome' => 'nullable|string', 'next_at' => 'nullable|date', 'next_location' => 'nullable|string|max:255', 'next_title' => 'nullable|string|max:255']);
        $next = DB::transaction(function () use ($t, $d) {
            $t->update(['status' => $d['status'], 'outcome' => $d['outcome'] ?? $t->outcome, 'done_at' => now()]);
            if (! empty($d['next_at'])) {
                return ProjectTask::create([
                    'project_id' => $t->project_id, 'kind' => $t->kind, 'due_at' => $d['next_at'],
                    'title' => $d['next_title'] ?? ($t->kind === 'hearing' ? 'جلسة '.\Carbon\Carbon::parse($d['next_at'])->format('Y-m-d') : $t->title),
                    'location' => $d['next_location'] ?? $t->location, 'assigned_to' => $t->assigned_to, 'priority' => $t->priority,
                ]);
            }

            return null;
        });

        return response()->json(['task' => $t->fresh(), 'next' => $next]);
    }

    public function destroy(int $id): JsonResponse
    {
        ProjectTask::findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, ?ProjectTask $t = null): array
    {
        $tn = app('currentTenantId');
        $data = $request->validate([
            'project_id' => ['required', Rule::exists('projects', 'id')->where('tenant_id', $tn)],
            'kind' => ['nullable', Rule::in(['task', 'hearing'])], 'title' => 'required|string|max:255',
            'due_at' => 'nullable|date', 'location' => 'nullable|string|max:255',
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tn)],
            'status' => ['nullable', Rule::in(['todo', 'doing', 'done', 'postponed', 'cancelled'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])], 'notes' => 'nullable|string', 'outcome' => 'nullable|string',
        ]);
        $data['kind'] = $data['kind'] ?? $t?->kind ?? 'task';
        $data['status'] = $data['status'] ?? $t?->status ?? 'todo';
        $data['priority'] = $data['priority'] ?? 'normal';

        return $data;
    }
}
