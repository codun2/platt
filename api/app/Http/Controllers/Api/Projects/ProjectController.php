<?php

namespace App\Http\Controllers\Api\Projects;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Sequence;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\DocumentCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Cases (law) and projects (engineering): list, file, overview, fee billing. */
class ProjectController extends Controller
{
    public const STATUSES = ['open', 'in_progress', 'on_hold', 'closed', 'won', 'lost', 'settled'];
    private const CLOSED = ['closed', 'won', 'lost', 'settled'];

    public function summary(): JsonResponse
    {
        $now = now();
        $open = Project::whereNotIn('status', self::CLOSED);
        $unbilled = Timesheet::whereNull('invoice_id')->where('billable', true);

        return response()->json([
            'stats' => [
                'open' => (clone $open)->count(),
                'cases' => (clone $open)->where('kind', 'case')->count(),
                'projects' => (clone $open)->where('kind', 'project')->count(),
                'hearings_week' => ProjectTask::where('kind', 'hearing')->whereIn('status', ['todo', 'doing'])->whereBetween('due_at', [$now->copy()->startOfDay(), $now->copy()->addDays(7)->endOfDay()])->count(),
                'overdue_tasks' => ProjectTask::whereIn('status', ['todo', 'doing'])->where('due_at', '<', $now)->count(),
                'hours_month' => round((float) Timesheet::whereBetween('date', [$now->copy()->startOfMonth()->toDateString(), $now->toDateString()])->sum('hours'), 2),
                'unbilled_hours' => round((float) (clone $unbilled)->sum('hours'), 2),
                'unbilled_amount' => round((float) (clone $unbilled)->sum('amount'), 3),
            ],
            'upcoming' => ProjectTask::with('project:id,number,title,kind,court', 'assignee:id,name')->whereIn('status', ['todo', 'doing'])
                ->where('due_at', '>=', $now->copy()->startOfDay())->orderBy('due_at')->limit(10)->get(),
            'overdue' => ProjectTask::with('project:id,number,title,kind', 'assignee:id,name')->whereIn('status', ['todo', 'doing'])
                ->where('due_at', '<', $now)->orderBy('due_at')->limit(10)->get(),
        ]);
    }

    /** Users who can lead cases / be assigned / log hours. */
    public function people(): JsonResponse
    {
        return response()->json(User::where('tenant_id', app('currentTenantId'))->where('is_active', true)->orderBy('name')->get(['id', 'name']));
    }

    public function index(Request $request): JsonResponse
    {
        $q = Project::with('customer:id,name,phone', 'lead:id,name')
            ->withSum(['timesheets as hours' => fn ($t) => $t], 'hours')
            ->withSum(['timesheets as unbilled' => fn ($t) => $t->whereNull('invoice_id')->where('billable', true)], 'amount')
            ->withCount(['tasks as open_tasks' => fn ($t) => $t->whereIn('status', ['todo', 'doing'])])
            ->latest('id');
        if ($k = $request->query('kind')) {
            $q->where('kind', $k);
        }
        $status = $request->query('status', 'active');
        if ($status === 'active') {
            $q->whereNotIn('status', self::CLOSED);
        } elseif ($status !== 'all' && $status !== '') {
            $q->where('status', $status);
        }
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('title', 'like', "%$s%")->orWhere('number', 'like', "%$s%")->orWhere('court_case_no', 'like', "%$s%")
                ->orWhere('opponent', 'like', "%$s%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")));
        }
        foreach (['customer_id', 'lead_id', 'case_type'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }
        $kind = $request->query('kind');
        $base = Project::when($kind, fn ($w) => $w->where('kind', $kind));
        $stats = [
            'active' => (clone $base)->whereNotIn('status', self::CLOSED)->count(),
            'closed' => (clone $base)->whereIn('status', self::CLOSED)->count(),
            'won' => (clone $base)->where('status', 'won')->count(),
            'value' => round((float) (clone $base)->whereNotIn('status', self::CLOSED)->sum('contract_value'), 3),
        ];

        return response()->json(['page' => $q->paginate(30), 'stats' => $stats]);
    }

    public function show(int $id): JsonResponse
    {
        $p = Project::with([
            'customer', 'lead:id,name', 'creator:id,name',
            'tasks' => fn ($t) => $t->with('assignee:id,name')->orderByRaw("status in ('done','cancelled')")->orderBy('due_at'),
            'timesheets' => fn ($t) => $t->with('user:id,name', 'invoice:id,number,status')->latest('date')->latest('id'),
        ])->findOrFail($id);
        $p->setAttribute('totals', [
            'hours' => round((float) $p->timesheets->sum('hours'), 2),
            'amount' => round((float) $p->timesheets->sum('amount'), 3),
            'unbilled_hours' => round((float) $p->timesheets->where('billable', true)->whereNull('invoice_id')->sum('hours'), 2),
            'unbilled_amount' => round((float) $p->timesheets->where('billable', true)->whereNull('invoice_id')->sum('amount'), 3),
        ]);
        $p->setAttribute('invoices', Invoice::where('customer_id', $p->customer_id)->where('reference', $p->number)->latest('id')->get(['id', 'number', 'date', 'total', 'paid', 'status']));

        return response()->json($p);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $p = Project::create($data + [
            'number' => Sequence::next($data['kind'] === 'case' ? 'case' : 'project', $data['kind'] === 'case' ? 'CASE' : 'PRJ'),
            'created_by' => $request->user()->id,
        ]);

        return response()->json($p, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $p = Project::findOrFail($id);
        $data = $this->validated($request, $p);
        if (in_array($data['status'], self::CLOSED, true) && ! $p->closed_at) {
            $data['closed_at'] = now()->toDateString();
        } elseif (! in_array($data['status'], self::CLOSED, true)) {
            $data['closed_at'] = null;
        }
        $p->update($data);

        return response()->json($p->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $p = Project::findOrFail($id);
        abort_if($p->timesheets()->whereNotNull('invoice_id')->exists(), 422, 'لا يمكن الحذف: توجد ساعات مفوترة. أغلق الملف بدلاً من ذلك.');
        $p->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Fee invoice (draft) for the client: unbilled billable hours as lines,
     * plus an optional fixed amount (fixed fee / retainer installment).
     */
    public function bill(Request $request, int $id): JsonResponse
    {
        $p = Project::with('customer')->findOrFail($id);
        $d = $request->validate(['fixed_amount' => 'nullable|numeric|min:0', 'fixed_label' => 'nullable|string|max:200', 'include_hours' => 'boolean']);
        abort_if(! $p->customer_id, 422, 'حدّد العميل / الموكّل في الملف أولاً.');
        $entries = ($d['include_hours'] ?? true) ? $p->timesheets()->whereNull('invoice_id')->where('billable', true)->orderBy('date')->get() : collect();
        $lines = $entries->map(fn ($t) => [
            'description' => trim(($t->date->toDateString()).' — '.($t->description ?: 'ساعات عمل')),
            'qty' => (float) $t->hours, 'unit' => 'ساعة', 'unit_price' => (float) $t->rate,
        ])->all();
        if ((float) ($d['fixed_amount'] ?? 0) > 0) {
            $lines[] = ['description' => $d['fixed_label'] ?: ($p->kind === 'case' ? 'أتعاب القضية '.$p->number : 'أتعاب المشروع '.$p->number), 'qty' => 1, 'unit_price' => (float) $d['fixed_amount']];
        }
        abort_if(! $lines, 422, 'لا توجد ساعات غير مفوترة ولا مبلغ ثابت لإصدار فاتورة.');

        $inv = DB::transaction(function () use ($p, $lines, $entries, $request) {
            $tax = (float) (app('currentTenant')->tax_rate ?? 0);
            $calc = DocumentCalculator::compute($lines, 'amount', 0, $tax);
            $inv = Invoice::create([
                'number' => Sequence::next('sale', 'INV'), 'status' => 'draft', 'customer_id' => $p->customer_id,
                'date' => now()->toDateString(), 'subject' => ($p->kind === 'case' ? 'أتعاب: ' : 'مشروع: ').$p->title, 'reference' => $p->number,
                'discount_type' => 'amount', 'discount_value' => 0, 'tax_rate' => $tax,
                'subtotal' => $calc['subtotal'], 'discount' => $calc['discount'], 'tax' => $calc['tax'], 'total' => $calc['total'],
                'created_by' => $request->user()->id,
            ]);
            foreach ($calc['lines'] as $l) {
                InvoiceItem::create(['invoice_id' => $inv->id] + $l);
            }
            Timesheet::whereIn('id', $entries->pluck('id'))->update(['invoice_id' => $inv->id]);

            return $inv;
        });

        return response()->json(['invoice_id' => $inv->id, 'number' => $inv->number, 'total' => $inv->total], 201);
    }

    private function validated(Request $request, ?Project $p = null): array
    {
        $t = app('currentTenantId');
        $data = $request->validate([
            'kind' => ['nullable', Rule::in(['project', 'case'])],
            'title' => 'required|string|max:255',
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $t)],
            'status' => ['nullable', Rule::in(self::STATUSES)], 'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'lead_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $t)],
            'start_date' => 'nullable|date', 'due_date' => 'nullable|date',
            'case_type' => 'nullable|string|max:20', 'court' => 'nullable|string|max:255', 'court_case_no' => 'nullable|string|max:60',
            'client_role' => ['nullable', Rule::in(['plaintiff', 'defendant', 'other'])], 'opponent' => 'nullable|string|max:255',
            'opponent_lawyer' => 'nullable|string|max:255', 'stage' => 'nullable|string|max:20',
            'location' => 'nullable|string|max:255', 'contract_value' => 'nullable|numeric|min:0', 'progress' => 'nullable|integer|min:0|max:100',
            'fee_type' => ['nullable', Rule::in(['hourly', 'fixed', 'retainer'])], 'fee_amount' => 'nullable|numeric|min:0', 'hourly_rate' => 'nullable|numeric|min:0',
            'description' => 'nullable|string', 'notes' => 'nullable|string',
        ]);
        $data['kind'] = $data['kind'] ?? $p?->kind ?? 'project';
        $data['status'] = $data['status'] ?? $p?->status ?? 'open';
        $data['priority'] = $data['priority'] ?? 'normal';
        $data['fee_type'] = $data['fee_type'] ?? 'hourly';
        foreach (['contract_value', 'fee_amount', 'hourly_rate', 'progress'] as $k) {
            $data[$k] = $data[$k] ?? 0;
        }

        return $data;
    }
}
