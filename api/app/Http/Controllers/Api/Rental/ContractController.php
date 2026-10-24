<?php

namespace App\Http\Controllers\Api\Rental;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\RentalAsset;
use App\Models\RentalContract;
use App\Models\RentalInstallment;
use App\Models\Sequence;
use App\Services\LedgerService;
use App\Services\RentalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/** Lease / installment contracts, their schedule, collection and closing. */
class ContractController extends Controller
{
    public function __construct(private RentalService $rental) {}

    public function index(Request $request): JsonResponse
    {
        $today = now()->toDateString();
        $q = RentalContract::with('asset:id,name,type,parent_id', 'asset.parent:id,name', 'customer:id,name,phone')
            ->withSum(['installments as overdue' => fn ($i) => $i->whereIn('status', ['due', 'partial'])->where('due_date', '<', $today)], DB::raw('amount - paid'))
            ->latest('start_date')->latest('id');
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('number', 'like', "%$s%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%"))
                ->orWhereHas('asset', fn ($a) => $a->where('name', 'like', "%$s%")));
        }
        foreach (['status', 'kind', 'asset_id', 'customer_id'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }
        if ($request->boolean('expiring')) {
            $q->where('status', 'active')->whereBetween('end_date', [$today, now()->addDays(60)->toDateString()]);
        }
        if ($request->boolean('overdue')) {
            $q->whereHas('installments', fn ($i) => $i->whereIn('status', ['due', 'partial'])->where('due_date', '<', $today));
        }
        $page = $q->paginate(30);
        $active = RentalContract::where('status', 'active');
        $stats = [
            'active' => (clone $active)->count(),
            'expiring' => (clone $active)->whereBetween('end_date', [$today, now()->addDays(60)->toDateString()])->count(),
            'monthly_value' => round((float) (clone $active)->get()->sum(fn ($c) => $this->monthly($c)), 3),
            'overdue' => round((float) RentalInstallment::whereIn('status', ['due', 'partial'])->where('due_date', '<', $today)
                ->whereHas('contract', fn ($c) => $c->where('status', 'active'))->sum(DB::raw('amount - paid')), 3),
        ];

        return response()->json(['page' => $page, 'stats' => $stats]);
    }

    public function show(int $id): JsonResponse
    {
        $c = RentalContract::with(['asset.owner:id,name,phone', 'asset.parent:id,name', 'customer', 'installments', 'payments.user:id,name', 'creator:id,name'])->findOrFail($id);
        $today = now()->toDateString();
        $c->setAttribute('overdue', round((float) $c->installments->filter(fn ($i) => in_array($i->status, ['due', 'partial']) && $i->due_date->toDateString() < $today)->sum(fn ($i) => (float) $i->amount - (float) $i->paid), 3));
        $c->setAttribute('next_due', $c->installments->first(fn ($i) => in_array($i->status, ['due', 'partial'])));

        return response()->json($c);
    }

    /** Schedule preview for the new-contract form. */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start_date' => 'required|date', 'end_date' => 'required|date', 'frequency' => ['required', Rule::in(RentalService::FREQUENCIES)],
            'rent_amount' => 'required|numeric|min:0', 'down_payment' => 'nullable|numeric|min:0',
        ]);
        try {
            $rows = $this->rental->schedule($data);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json(['rows' => $rows, 'count' => count($rows), 'total' => round(array_sum(array_column($rows, 'amount')), 3)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $asset = RentalAsset::findOrFail($data['asset_id']);
        abort_if($asset->type === 'building', 422, 'اختر وحدة داخل البناية وليس البناية نفسها.');
        abort_if(RentalContract::where('asset_id', $asset->id)->where('status', 'active')
            ->where('start_date', '<=', $data['end_date'])->where('end_date', '>=', $data['start_date'])->exists(),
            422, 'يوجد عقد ساري على هذا الأصل في نفس الفترة.');

        try {
            $c = DB::transaction(function () use ($data, $request, $asset) {
                $data['customer_id'] = $data['customer_id'] ?? $this->quickCustomer($request);
                unset($data['new_customer']);
                $c = RentalContract::create($data + [
                    'number' => Sequence::next('rental_contract', 'RC'), 'status' => 'active', 'created_by' => $request->user()->id,
                ]);
                $this->rental->generate($c);
                $this->rental->syncAssetStatus($asset);

                return $c;
            });
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json($c->fresh(), 201);
    }

    /** Edit details; schedule fields only while nothing has been collected. */
    public function update(Request $request, int $id): JsonResponse
    {
        $c = RentalContract::findOrFail($id);
        abort_if($c->status !== 'active', 422, 'لا يمكن تعديل عقد منتهٍ.');
        $data = $this->validated($request, $c);
        unset($data['new_customer']);
        $scheduleChanged = collect(['start_date', 'end_date', 'frequency', 'rent_amount', 'down_payment'])
            ->contains(fn ($k) => (string) ($data[$k] ?? '') !== (string) ($k === 'start_date' || $k === 'end_date' ? $c->{$k}?->toDateString() : $c->{$k}));
        try {
            DB::transaction(function () use ($c, $data, $scheduleChanged) {
                $oldAsset = $c->asset;
                $c->update($data);
                if ($scheduleChanged) {
                    $this->rental->generate($c);
                }
                $this->rental->syncAssetStatus($oldAsset);
                $this->rental->syncAssetStatus($c->fresh()->asset);
            });
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json($c->fresh());
    }

    public function collect(Request $request, int $id): JsonResponse
    {
        $c = RentalContract::findOrFail($id);
        $d = $request->validate([
            'amount' => 'required|numeric|gt:0', 'method' => 'required|in:cash,card,transfer', 'date' => 'nullable|date',
            'installment_id' => ['nullable', Rule::exists('rental_installments', 'id')->where('contract_id', $c->id)],
            'reference' => 'nullable|string|max:60', 'note' => 'nullable|string|max:255',
        ]);
        try {
            $this->rental->collect($c, (float) $d['amount'], $d['method'], $d['date'] ?? now()->toDateString(), $d['installment_id'] ?? null, $d['reference'] ?? null, $d['note'] ?? null, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return $this->show($id);
    }

    public function deposit(Request $request, int $id): JsonResponse
    {
        $c = RentalContract::findOrFail($id);
        $d = $request->validate(['kind' => 'required|in:deposit,deposit_return', 'amount' => 'required|numeric|gt:0', 'method' => 'required|in:cash,card,transfer', 'date' => 'nullable|date', 'note' => 'nullable|string|max:255']);
        try {
            $this->rental->deposit($c, $d['kind'], (float) $d['amount'], $d['method'], $d['date'] ?? now()->toDateString(), $d['note'] ?? null, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return $this->show($id);
    }

    /** End (expired) or terminate early. */
    public function close(Request $request, int $id): JsonResponse
    {
        $c = RentalContract::findOrFail($id);
        abort_if($c->status !== 'active', 422, 'العقد غير ساري.');
        $d = $request->validate(['status' => 'required|in:ended,terminated', 'date' => 'nullable|date']);
        $this->rental->close($c, $d['status'], $d['date'] ?? now()->toDateString());

        return $this->show($id);
    }

    /** Delete only a contract with no money movement. */
    public function destroy(int $id): JsonResponse
    {
        $c = RentalContract::withCount('payments')->findOrFail($id);
        abort_if($c->payments_count > 0, 422, 'لا يمكن حذف عقد عليه دفعات. أنهِ العقد بدلاً من ذلك.');
        $asset = $c->asset;
        $c->delete();
        $this->rental->syncAssetStatus($asset);

        return response()->json(['ok' => true]);
    }

    /* ------------------------------------------------------------------ */

    private function monthly(RentalContract $c): float
    {
        $per = ['daily' => 30, 'weekly' => 30 / 7, 'monthly' => 1, 'quarterly' => 1 / 3, 'semiannual' => 1 / 6, 'annual' => 1 / 12, 'once' => 0][$c->frequency] ?? 1;

        return (float) $c->rent_amount * $per;
    }

    private function quickCustomer(Request $request): int
    {
        $n = $request->input('new_customer', []);
        abort_if(empty($n['name']), 422, 'اختر المستأجر أو أدخل اسم مستأجر جديد.');

        return Customer::create([
            'name' => $n['name'], 'phone' => $n['phone'] ?? null, 'id_number' => $n['id_number'] ?? null,
            'customer_type' => 'individual', 'is_active' => true,
        ])->id;
    }

    private function validated(Request $request, ?RentalContract $c = null): array
    {
        $t = app('currentTenantId');
        $data = $request->validate([
            'kind' => ['nullable', Rule::in(['lease', 'installment'])],
            'asset_id' => ['required', Rule::exists('rental_assets', 'id')->where('tenant_id', $t)],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $t)],
            'new_customer' => 'nullable|array', 'new_customer.name' => 'nullable|string|max:150', 'new_customer.phone' => 'nullable|string|max:30', 'new_customer.id_number' => 'nullable|string|max:40',
            'start_date' => 'required|date', 'end_date' => 'required|date|after_or_equal:start_date',
            'frequency' => ['required', Rule::in(RentalService::FREQUENCIES)],
            'rent_amount' => 'required|numeric|min:0', 'down_payment' => 'nullable|numeric|min:0', 'deposit' => 'nullable|numeric|min:0',
            'terms' => 'nullable|string', 'notes' => 'nullable|string',
        ]);
        $data['kind'] = $data['kind'] ?? $c?->kind ?? 'lease';
        $data['down_payment'] = $data['down_payment'] ?? 0;
        $data['deposit'] = $data['deposit'] ?? 0;
        if ($c && empty($data['customer_id'])) {
            $data['customer_id'] = $c->customer_id;
        }

        return $data;
    }
}
