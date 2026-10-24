<?php

namespace App\Http\Controllers\Api\Rental;

use App\Http\Controllers\Controller;
use App\Models\RentalOwner;
use App\Models\RentalPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Owners & brokers whose properties / vehicles the tenant manages. */
class OwnerController extends Controller
{
    private const RULES = [
        'name' => 'required|string|max:150', 'type' => 'required|in:owner,broker', 'phone' => 'nullable|string|max:30',
        'email' => 'nullable|email|max:150', 'national_id' => 'nullable|string|max:40', 'commission_rate' => 'nullable|numeric|min:0|max:100',
        'bank_name' => 'nullable|string|max:100', 'iban' => 'nullable|string|max:40', 'notes' => 'nullable|string', 'is_active' => 'boolean',
    ];

    public function index(Request $request): JsonResponse
    {
        $q = RentalOwner::withCount('assets')->orderBy('name');
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%"));
        }
        if ($t = $request->query('type')) {
            $q->where('type', $t);
        }

        return response()->json($q->get());
    }

    /** Owner statement: their assets, and rent collected on them in the period. */
    public function show(Request $request, int $id): JsonResponse
    {
        $o = RentalOwner::with(['assets' => fn ($q) => $q->with('activeContract.customer:id,name')->orderBy('name')])->findOrFail($id);
        $from = $request->query('from', now()->startOfYear()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $assetIds = $o->assets->pluck('id');
        $payments = RentalPayment::with('contract:id,number,asset_id,customer_id', 'contract.asset:id,name', 'contract.customer:id,name')
            ->where('kind', 'rent')->whereBetween('date', [$from, $to])
            ->whereHas('contract', fn ($q) => $q->whereIn('asset_id', $assetIds))
            ->orderByDesc('date')->get();
        $collected = round((float) $payments->sum('amount'), 3);
        $commission = round($collected * (float) $o->commission_rate / 100, 3);

        return response()->json([
            'owner' => $o, 'payments' => $payments, 'from' => $from, 'to' => $to,
            'stats' => ['collected' => $collected, 'commission' => $commission, 'net' => round($collected - $commission, 3)],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(RentalOwner::create($this->validated($request)), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $o = RentalOwner::findOrFail($id);
        $o->update($this->validated($request));

        return response()->json($o);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate(self::RULES);
        $data['commission_rate'] = $data['commission_rate'] ?? 0;

        return $data;
    }

    public function destroy(int $id): JsonResponse
    {
        $o = RentalOwner::withCount('assets')->findOrFail($id);
        abort_if($o->assets_count > 0, 422, 'لا يمكن حذف مالك مرتبط بأصول. انقل الأصول أولاً.');
        $o->delete();

        return response()->json(['ok' => true]);
    }
}
