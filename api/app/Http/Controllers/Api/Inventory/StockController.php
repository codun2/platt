<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    /** Stock matrix: items × warehouses (optionally one warehouse). */
    public function levels(Request $request): JsonResponse
    {
        $q = StockLevel::with('item:id,name,sku,min_stock,cost_price', 'warehouse:id,name');
        if ($w = $request->query('warehouse_id')) {
            $q->where('warehouse_id', $w);
        }
        if ($s = $request->query('q')) {
            $q->whereHas('item', fn ($x) => $x->where('name', 'like', "%$s%")->orWhere('sku', 'like', "%$s%"));
        }

        $rows = $q->orderBy('warehouse_id')->get();
        $value = $rows->sum(fn ($r) => (float) $r->qty * (float) ($r->item->cost_price ?? 0));

        return response()->json(['levels' => $rows, 'stock_value' => round($value, 3)]);
    }

    public function lowStock(): JsonResponse
    {
        $items = Item::where('track_stock', true)->where('is_active', true)
            ->withSum('stockLevels as stock', 'qty')
            ->get()
            ->filter(fn ($i) => (float) ($i->stock ?? 0) <= (float) $i->min_stock)
            ->values();

        return response()->json($items);
    }

    public function movements(Request $request): JsonResponse
    {
        $q = StockMovement::with('item:id,name,sku', 'warehouse:id,name', 'user:id,name')->orderByDesc('id');
        if ($i = $request->query('item_id')) {
            $q->where('item_id', $i);
        }
        if ($w = $request->query('warehouse_id')) {
            $q->where('warehouse_id', $w);
        }
        if ($t = $request->query('type')) {
            $q->where('type', $t);
        }
        if ($from = $request->query('from')) {
            $q->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('created_at', '<=', $to);
        }

        return response()->json($q->paginate(50));
    }

    /** Stock count: set absolute quantity for one item in a warehouse. */
    public function adjust(Request $request, InventoryService $inv): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'qty' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $item = Item::findOrFail($data['item_id']);

        $m = DB::transaction(fn () => $inv->adjustTo($item, (int) $data['warehouse_id'], (float) $data['qty'], $data['note'] ?? 'جرد'));

        return response()->json(['ok' => true, 'movement' => $m]);
    }

    public function transfer(Request $request, InventoryService $inv): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'from_warehouse_id' => ['required', 'integer'],
            'to_warehouse_id' => ['required', 'integer'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $item = Item::findOrFail($data['item_id']);
        $inv->transfer($item, (int) $data['from_warehouse_id'], (int) $data['to_warehouse_id'], (float) $data['qty'], $data['note'] ?? null);

        return response()->json(['ok' => true]);
    }
}
