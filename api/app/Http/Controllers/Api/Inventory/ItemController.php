<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Item::with('category:id,name', 'unit:id,name,symbol')
            ->withSum('stockLevels as stock', 'qty')
            ->orderBy('name');

        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('sku', 'like', "%$s%")->orWhere('barcode', 'like', "%$s%"));
        }
        if ($c = $request->query('category_id')) {
            $q->where('category_id', $c);
        }
        if ($request->boolean('active_only')) {
            $q->where('is_active', true);
        }
        if ($request->boolean('low_stock')) {
            $q->where('track_stock', true)->having('stock', '<=', DB::raw('items.min_stock'));
        }

        return response()->json($request->boolean('all') ? $q->get() : $q->paginate(50));
    }

    /** Lookup used by POS / purchase lines: name, sku or barcode, with stock in a warehouse. */
    public function lookup(Request $request, InventoryService $inv): JsonResponse
    {
        $s = trim((string) $request->query('q'));
        $wh = (int) ($request->query('warehouse_id') ?: $inv->defaultWarehouseId());

        $q = Item::with('category:id,name', 'unit:id,name,symbol')
            ->withSum(['stockLevels as stock' => fn ($x) => $x->where('warehouse_id', $wh)], 'qty')
            ->where('is_active', true);

        if ($s !== '') {
            // Exact barcode hit first → single item (scanner flow).
            $exact = (clone $q)->where('barcode', $s)->first();
            if ($exact) {
                return response()->json(['exact' => true, 'items' => [$exact]]);
            }
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('sku', 'like', "%$s%")->orWhere('barcode', 'like', "%$s%"));
        }
        if ($c = $request->query('category_id')) {
            $q->where('category_id', $c);
        }

        return response()->json(['exact' => false, 'items' => $q->orderBy('name')->limit(60)->get()]);
    }

    public function show(int $id): JsonResponse
    {
        $item = Item::with('category:id,name', 'unit:id,name,symbol', 'supplier:id,name,phone')->withSum('stockLevels as stock', 'qty')->findOrFail($id);
        $levels = $item->stockLevels()->with('warehouse:id,name')->get();
        $movements = $item->movements()->with('warehouse:id,name', 'user:id,name')->orderByDesc('id')->limit(100)->get();
        $purchases = \App\Models\PurchaseItem::where('item_id', $item->id)->with('purchase:id,number,date,status,supplier_id', 'purchase.supplier:id,name')
            ->orderByDesc('id')->limit(20)->get(['id', 'purchase_id', 'qty', 'unit_cost', 'total']);

        return response()->json(['item' => $item, 'levels' => $levels, 'movements' => $movements, 'purchases' => $purchases]);
    }

    public function store(Request $request, InventoryService $inv): JsonResponse
    {
        $data = $this->validated($request);
        $opening = (float) ($data['opening_qty'] ?? 0);
        unset($data['opening_qty']);
        $data['track_stock'] = $data['track_stock'] ?? true;
        $data['is_active'] = $data['is_active'] ?? true;

        $item = DB::transaction(function () use ($data, $opening, $inv) {
            $item = Item::create($data);
            if ($opening > 0) {
                $inv->move($item, $inv->defaultWarehouseId(), $opening, 'opening', ['unit_cost' => $item->cost_price, 'note' => 'رصيد افتتاحي']);
            }

            return $item;
        });

        return response()->json($item->load('category:id,name', 'unit:id,name,symbol'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = Item::findOrFail($id);
        $data = $this->validated($request, $item);
        unset($data['opening_qty']);
        $item->update($data);

        return response()->json($item->fresh()->load('category:id,name', 'unit:id,name,symbol'));
    }

    public function destroy(int $id): JsonResponse
    {
        $item = Item::findOrFail($id);
        if ($item->movements()->exists()) {
            $item->update(['is_active' => false]);

            return response()->json(['ok' => true, 'deactivated' => true]);
        }
        $item->delete();

        return response()->json(['ok' => true]);
    }

    /** Lookups for the item form: categories, units, warehouses. */
    public function meta(): JsonResponse
    {
        return response()->json([
            'categories' => Category::orderBy('sort')->orderBy('name')->get(['id', 'name', 'parent_id']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'symbol']),
            'warehouses' => Warehouse::where('is_active', true)->orderByDesc('is_default')->get(['id', 'name', 'is_default']),
        ]);
    }

    private function validated(Request $request, ?Item $item = null): array
    {
        $tenantId = app('currentTenantId');

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:60', \Illuminate\Validation\Rule::unique('items', 'sku')->where('tenant_id', $tenantId)->ignore($item?->id)],
            'barcode' => ['nullable', 'string', 'max:80', \Illuminate\Validation\Rule::unique('items', 'barcode')->where('tenant_id', $tenantId)->ignore($item?->id)],
            'brand' => ['nullable', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', \Illuminate\Validation\Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
            'unit_id' => ['nullable', \Illuminate\Validation\Rule::exists('units', 'id')->where('tenant_id', $tenantId)],
            'supplier_id' => ['nullable', \Illuminate\Validation\Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'track_stock' => ['boolean'],
            'track_expiry' => ['boolean'],   // expiry add-on
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'reorder_qty' => ['nullable', 'numeric', 'min:0'],
            'max_stock' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:80'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
            'opening_qty' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
