<?php

namespace App\Http\Controllers\Api\Rental;

use App\Http\Controllers\Controller;
use App\Models\RentalAsset;
use App\Services\RentalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Leasable assets: buildings and their units, standalone properties, vehicles. */
class AssetController extends Controller
{
    public const TYPES = ['building', 'apartment', 'villa', 'shop', 'office', 'warehouse', 'land', 'vehicle', 'other'];

    public function index(Request $request): JsonResponse
    {
        $q = RentalAsset::with(['owner:id,name', 'parent:id,name', 'activeContract' => fn ($c) => $c->select(array_map(fn ($col) => "rental_contracts.$col", ['id', 'asset_id', 'customer_id', 'number', 'end_date', 'rent_amount', 'frequency']))->with('customer:id,name')])
            ->withCount('units')->orderBy('parent_id')->orderBy('name');
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%")->orWhere('plate_no', 'like', "%$s%")->orWhere('address', 'like', "%$s%"));
        }
        foreach (['status', 'type', 'owner_id', 'parent_id'] as $f) {
            if ($v = $request->query($f)) {
                $q->where($f, $v);
            }
        }
        if ($request->boolean('leasable')) {
            $q->where('type', '!=', 'building');   // buildings are containers; units are leased
        }
        $all = (clone $q)->get();
        $stats = [
            'total' => $all->where('type', '!=', 'building')->count(),
            'rented' => $all->where('status', 'rented')->count(),
            'available' => $all->where('status', 'available')->where('type', '!=', 'building')->count(),
            'maintenance' => $all->where('status', 'maintenance')->count(),
        ];

        return response()->json(['data' => $all, 'stats' => $stats]);
    }

    public function show(int $id): JsonResponse
    {
        $a = RentalAsset::with([
            'owner', 'parent:id,name',
            'units' => fn ($q) => $q->with('activeContract.customer:id,name')->orderBy('name'),
            'contracts' => fn ($q) => $q->with('customer:id,name,phone')->latest('start_date'),
        ])->findOrFail($id);

        return response()->json($a);
    }

    public function store(Request $request): JsonResponse
    {
        $a = RentalAsset::create($this->validated($request));

        return response()->json($a, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $a = RentalAsset::findOrFail($id);
        $data = $this->validated($request, $a);
        abort_if(($data['parent_id'] ?? null) == $a->id, 422, 'لا يمكن أن يكون الأصل تابعاً لنفسه.');
        $a->update($data);
        app(RentalService::class)->syncAssetStatus($a->fresh());

        return response()->json($a->fresh());
    }

    public function destroy(int $id): JsonResponse
    {
        $a = RentalAsset::withCount(['contracts', 'units'])->findOrFail($id);
        abort_if($a->contracts_count > 0, 422, 'لا يمكن حذف أصل له عقود. يمكنك إيقافه بدلاً من ذلك.');
        abort_if($a->units_count > 0, 422, 'احذف أو انقل الوحدات التابعة أولاً.');
        $a->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request, ?RentalAsset $a = null): array
    {
        $t = app('currentTenantId');

        $data = $request->validate([
            'parent_id' => ['nullable', Rule::exists('rental_assets', 'id')->where('tenant_id', $t)],
            'owner_id' => ['nullable', Rule::exists('rental_owners', 'id')->where('tenant_id', $t)],
            'code' => 'nullable|string|max:40', 'name' => 'required|string|max:150',
            'type' => ['required', Rule::in(self::TYPES)],
            'status' => ['nullable', Rule::in(['available', 'rented', 'maintenance', 'inactive'])],
            'city' => 'nullable|string|max:80', 'address' => 'nullable|string|max:255',
            'area' => 'nullable|numeric|min:0', 'rooms' => 'nullable|integer|min:0|max:999', 'bathrooms' => 'nullable|integer|min:0|max:999',
            'floor' => 'nullable|string|max:20', 'furnished' => 'boolean',
            'electricity_meter' => 'nullable|string|max:40', 'water_meter' => 'nullable|string|max:40',
            'plate_no' => 'nullable|string|max:30', 'make' => 'nullable|string|max:60', 'model' => 'nullable|string|max:60',
            'year' => 'nullable|integer|min:1950|max:2100', 'color' => 'nullable|string|max:30', 'vin' => 'nullable|string|max:40', 'odometer' => 'nullable|integer|min:0',
            'rent_amount' => 'nullable|numeric|min:0', 'rent_frequency' => ['nullable', Rule::in(RentalService::FREQUENCIES)], 'deposit' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ]);
        // Empty form fields arrive as null; the columns have defaults.
        $data['status'] = $data['status'] ?? $a?->status ?? 'available';
        $data['rent_amount'] = $data['rent_amount'] ?? 0;
        $data['deposit'] = $data['deposit'] ?? 0;
        $data['rent_frequency'] = $data['rent_frequency'] ?? 'monthly';
        $data['furnished'] = (bool) ($data['furnished'] ?? false);

        return $data;
    }
}
