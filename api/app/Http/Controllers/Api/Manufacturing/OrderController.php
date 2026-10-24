<?php

namespace App\Http\Controllers\Api\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\Bom;
use App\Models\Item;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderLine;
use App\Models\Sequence;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Production orders: plan → start → complete (consume materials, add product). */
class OrderController extends Controller
{
    public function __construct(private InventoryService $inv) {}

    public function summary(): JsonResponse
    {
        $orders = ProductionOrder::with('item:id,name', 'lines.item:id,name,track_stock')->whereIn('status', ['planned', 'in_progress'])->get();
        // Material needed by all open orders vs stock on hand → shortages.
        $need = [];
        foreach ($orders as $o) {
            foreach ($o->lines as $l) {
                $need[$l->item_id] = ($need[$l->item_id] ?? 0) + (float) $l->qty_required;
            }
        }
        $shortages = [];
        foreach ($need as $itemId => $qty) {
            $item = Item::find($itemId);
            if ($item && $item->track_stock) {
                $have = $this->inv->qty($item);
                if ($have + 0.0005 < $qty) {
                    $shortages[] = ['item_id' => $itemId, 'name' => $item->name, 'need' => round($qty, 3), 'have' => round($have, 3), 'missing' => round($qty - $have, 3)];
                }
            }
        }
        $month = ProductionOrder::where('status', 'completed')->where('completed_at', '>=', now()->startOfMonth());

        return response()->json([
            'stats' => [
                'planned' => $orders->where('status', 'planned')->count(), 'in_progress' => $orders->where('status', 'in_progress')->count(),
                'completed_month' => (clone $month)->count(), 'produced_value' => round((float) (clone $month)->get()->sum(fn ($o) => (float) $o->actual_qty * (float) $o->unit_cost), 3),
                'recipes' => Bom::where('is_active', true)->count(), 'shortages' => count($shortages),
            ],
            'open' => $orders->sortBy('planned_date')->values()->map(fn ($o) => $o->only(['id', 'number', 'status', 'planned_qty', 'planned_date']) + ['item' => $o->item?->name]),
            'shortages' => $shortages,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $q = ProductionOrder::with('item:id,name', 'item.unit:id,symbol,name', 'bom:id,name')->latest('id');
        $st = $request->query('status', 'open');
        if ($st === 'open') {
            $q->whereIn('status', ['planned', 'in_progress']);
        } elseif ($st && $st !== 'all') {
            $q->where('status', $st);
        }
        if ($i = $request->query('item_id')) {
            $q->where('item_id', $i);
        }

        return response()->json($q->paginate(30));
    }

    public function show(int $id): JsonResponse
    {
        $o = ProductionOrder::with('item.unit:id,name,symbol', 'bom', 'warehouse:id,name', 'lines.item.unit:id,name,symbol', 'creator:id,name')->findOrFail($id);
        $wh = $o->warehouse_id ?: $this->inv->defaultWarehouseId();
        foreach ($o->lines as $l) {
            $l->setAttribute('available', $l->item?->track_stock ? round($this->inv->qty($l->item, $wh), 3) : null);
        }

        return response()->json($o);
    }

    public function store(Request $request): JsonResponse
    {
        $t = app('currentTenantId');
        $d = $request->validate([
            'bom_id' => ['required', Rule::exists('boms', 'id')->where('tenant_id', $t)], 'planned_qty' => 'required|numeric|gt:0',
            'planned_date' => 'nullable|date', 'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('tenant_id', $t)], 'notes' => 'nullable|string',
        ]);
        $bom = Bom::with('lines.item')->findOrFail($d['bom_id']);
        $o = DB::transaction(function () use ($d, $bom, $request) {
            $factor = (float) $d['planned_qty'] / (float) $bom->output_qty;
            $o = ProductionOrder::create($d + ['number' => Sequence::next('production', 'PO'), 'item_id' => $bom->item_id, 'status' => 'planned', 'created_by' => $request->user()->id,
                'extra_cost' => round((float) $bom->extra_cost * $factor, 3)]);
            foreach ($bom->lines as $l) {
                ProductionOrderLine::create(['order_id' => $o->id, 'item_id' => $l->item_id, 'qty_required' => round((float) $l->qty * (1 + (float) $l->waste_pct / 100) * $factor, 3), 'unit_cost' => (float) ($l->item->cost_price ?? 0)]);
            }

            return $o;
        });

        return response()->json($o, 201);
    }

    public function start(int $id): JsonResponse
    {
        $o = ProductionOrder::findOrFail($id);
        abort_if($o->status !== 'planned', 422, 'الأمر ليس في حالة «مخطط».');
        $o->update(['status' => 'in_progress', 'started_at' => now()]);

        return $this->show($id);
    }

    /**
     * Finish: consume materials (actual or required quantities) and receive the
     * finished product at the real cost per unit (weighted into the item cost).
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $o = ProductionOrder::with('lines.item', 'item')->findOrFail($id);
        abort_if(! in_array($o->status, ['planned', 'in_progress'], true), 422, 'هذا الأمر مغلق.');
        $d = $request->validate(['actual_qty' => 'required|numeric|gt:0', 'consumed' => 'nullable|array', 'consumed.*' => 'numeric|min:0']);
        $wh = $o->warehouse_id ?: $this->inv->defaultWarehouseId();

        DB::transaction(function () use ($o, $d, $wh) {
            $material = 0.0;
            foreach ($o->lines as $l) {
                $used = (float) ($d['consumed'][$l->id] ?? $l->qty_required);
                $cost = (float) ($l->item->cost_price ?? 0);
                if ($used > 0) {
                    $this->inv->move($l->item, $wh, -$used, 'production_out', ['ref_type' => 'production_order', 'ref_id' => $o->id, 'unit_cost' => $cost, 'note' => $o->number]);
                }
                $l->update(['qty_consumed' => $used, 'unit_cost' => $cost]);
                $material += $used * $cost;
            }
            $qty = (float) $d['actual_qty'];
            $unitCost = round(($material + (float) $o->extra_cost) / $qty, 3);
            // Weighted-average cost of the finished product.
            $product = $o->item;
            $onHand = max(0, $this->inv->qty($product));
            $newCost = ($onHand + $qty) > 0 ? round(($onHand * (float) $product->cost_price + $qty * $unitCost) / ($onHand + $qty), 3) : $unitCost;
            $this->inv->move($product, $wh, $qty, 'production_in', ['ref_type' => 'production_order', 'ref_id' => $o->id, 'unit_cost' => $unitCost, 'note' => $o->number]);
            $product->update(['cost_price' => $newCost]);
            $o->update(['status' => 'completed', 'completed_at' => now(), 'actual_qty' => $qty, 'material_cost' => round($material, 3), 'unit_cost' => $unitCost, 'started_at' => $o->started_at ?? now()]);
        });

        return $this->show($id);
    }

    public function cancel(int $id): JsonResponse
    {
        $o = ProductionOrder::findOrFail($id);
        abort_if(! in_array($o->status, ['planned', 'in_progress'], true), 422, 'لا يمكن إلغاء أمر مكتمل.');
        $o->update(['status' => 'cancelled']);

        return $this->show($id);
    }
}
