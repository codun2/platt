<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sequence;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Purchase::with('supplier:id,name', 'warehouse:id,name', 'creator:id,name')->withCount('items')->orderByDesc('id');
        if ($st = $request->query('status')) {
            $q->where('status', $st);
        }
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('number', 'like', "%$s%")->orWhere('supplier_invoice', 'like', "%$s%")
                ->orWhereHas('supplier', fn ($x) => $x->where('name', 'like', "%$s%")));
        }
        if ($from = $request->query('from')) {
            $q->whereDate('date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('date', '<=', $to);
        }

        return response()->json($q->paginate(30));
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(Purchase::with('items.item:id,name,sku,unit_id', 'items.item.unit:id,symbol', 'supplier', 'warehouse:id,name', 'creator:id,name')->findOrFail($id));
    }

    public function store(Request $request, InventoryService $inv): JsonResponse
    {
        $data = $this->validated($request);

        $purchase = DB::transaction(function () use ($data, $request, $inv) {
            $p = Purchase::create([
                'number' => Sequence::next('purchase', 'PUR'),
                'supplier_id' => $data['supplier_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? $inv->defaultWarehouseId(),
                'date' => $data['date'],
                'supplier_invoice' => $data['supplier_invoice'] ?? null,
                'discount' => $data['discount'] ?? 0,
                'tax' => $data['tax'] ?? 0,
                'paid' => $data['paid'] ?? 0,
                'note' => $data['note'] ?? null,
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);
            $this->syncLines($p, $data['lines']);
            if (! empty($data['receive'])) {
                $this->doReceive($p, $inv);
            }

            return $p;
        });

        return response()->json($purchase->load('items.item:id,name,sku', 'supplier:id,name', 'warehouse:id,name'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $p = Purchase::findOrFail($id);
        if ($p->status !== 'draft') {
            return response()->json(['message' => 'لا يمكن تعديل فاتورة شراء مستلمة أو ملغاة.'], 422);
        }
        $data = $this->validated($request);

        DB::transaction(function () use ($p, $data) {
            $p->update([
                'supplier_id' => $data['supplier_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? $p->warehouse_id,
                'date' => $data['date'],
                'supplier_invoice' => $data['supplier_invoice'] ?? null,
                'discount' => $data['discount'] ?? 0,
                'tax' => $data['tax'] ?? 0,
                'paid' => $data['paid'] ?? 0,
                'note' => $data['note'] ?? null,
            ]);
            $p->items()->delete();
            $this->syncLines($p, $data['lines']);
        });

        return response()->json($p->fresh()->load('items.item:id,name,sku', 'supplier:id,name', 'warehouse:id,name'));
    }

    /** Receive a draft: stock in + update item cost price. */
    public function receive(int $id, InventoryService $inv): JsonResponse
    {
        $p = Purchase::with('items')->findOrFail($id);
        if ($p->status !== 'draft') {
            return response()->json(['message' => 'هذه الفاتورة ليست مسودة.'], 422);
        }
        DB::transaction(fn () => $this->doReceive($p, $inv));

        return response()->json($p->fresh()->load('items.item:id,name,sku'));
    }

    /** Cancel: a received purchase reverses its stock first. */
    public function cancel(int $id, InventoryService $inv): JsonResponse
    {
        $p = Purchase::with('items')->findOrFail($id);
        if ($p->status === 'cancelled') {
            return response()->json(['message' => 'الفاتورة ملغاة مسبقاً.'], 422);
        }
        DB::transaction(function () use ($p, $inv) {
            if ($p->status === 'received') {
                foreach ($p->items as $line) {
                    $inv->move(Item::findOrFail($line->item_id), $p->warehouse_id, -(float) $line->qty, 'purchase_cancel', ['ref_type' => 'purchase', 'ref_id' => $p->id, 'allow_negative' => true]);
                }
            }
            $p->update(['status' => 'cancelled']);
        });

        return response()->json($p->fresh());
    }

    public function pay(Request $request, int $id): JsonResponse
    {
        $p = Purchase::findOrFail($id);
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0']]);
        $p->update(['paid' => min((float) $p->total, (float) $p->paid + (float) $data['amount'])]);

        return response()->json($p->fresh());
    }

    private function doReceive(Purchase $p, InventoryService $inv): void
    {
        foreach ($p->items as $line) {
            $item = Item::findOrFail($line->item_id);
            $inv->move($item, $p->warehouse_id, (float) $line->qty, 'purchase', ['ref_type' => 'purchase', 'ref_id' => $p->id, 'unit_cost' => $line->unit_cost]);
            $item->update(['cost_price' => $line->unit_cost]);   // last-cost valuation
        }
        $p->update(['status' => 'received', 'received_at' => now()]);
    }

    private function syncLines(Purchase $p, array $lines): void
    {
        $subtotal = 0;
        foreach ($lines as $l) {
            $total = round((float) $l['qty'] * (float) $l['unit_cost'], 3);
            $subtotal += $total;
            PurchaseItem::create(['purchase_id' => $p->id, 'item_id' => $l['item_id'], 'qty' => $l['qty'], 'unit_cost' => $l['unit_cost'], 'total' => $total]);
        }
        $p->update(['subtotal' => $subtotal, 'total' => max(0, $subtotal - (float) $p->discount + (float) $p->tax)]);
    }

    private function validated(Request $request): array
    {
        $tenantId = app('currentTenantId');

        return $request->validate([
            'supplier_id' => ['nullable', \Illuminate\Validation\Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)],
            'warehouse_id' => ['nullable', \Illuminate\Validation\Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'date' => ['required', 'date'],
            'supplier_invoice' => ['nullable', 'string', 'max:80'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
            'receive' => ['boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', \Illuminate\Validation\Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);
    }
}
