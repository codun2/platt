<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;

/** Supplier profile page: supplier + purchase history + balances (CRUD lives in SimpleCrudController). */
class SupplierController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $s = Supplier::withCount('purchases', 'items')->findOrFail($id);
        $purchases = $s->purchases()->with('warehouse:id,name')->withCount('items')->orderByDesc('date')->orderByDesc('id')->limit(50)->get();
        $open = $s->purchases()->where('status', '!=', 'cancelled');
        $stats = [
            'purchases_total' => (float) (clone $open)->sum('total'),
            'paid_total' => (float) (clone $open)->sum('paid'),
            'balance' => round((float) (clone $open)->selectRaw('coalesce(sum(total - paid),0) as d')->value('d') + (float) $s->opening_balance, 3),
            'last_purchase' => optional((clone $open)->orderByDesc('date')->first())->date?->toDateString(),
        ];

        return response()->json(['supplier' => $s, 'purchases' => $purchases, 'stats' => $stats]);
    }
}
