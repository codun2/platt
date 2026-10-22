<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Sequence;
use App\Models\Shift;
use App\Services\InventoryService;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SaleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Sale::with('customer:id,name,phone', 'warehouse:id,name', 'creator:id,name')->withCount('items')->orderByDesc('id');
        if ($st = $request->query('status')) {
            $q->where('status', $st);
        }
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('number', 'like', "%$s%")->orWhereHas('customer', fn ($x) => $x->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")));
        }
        if ($c = $request->query('customer_id')) {
            $q->where('customer_id', $c);
        }
        if ($from = $request->query('from')) {
            $q->whereDate('date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('date', '<=', $to);
        }
        if ($request->boolean('unpaid')) {
            $q->where('status', 'completed')->whereColumn('paid', '<', 'total');
        }

        return response()->json($q->paginate(30));
    }

    public function show(int $id): JsonResponse
    {
        $sale = Sale::with('items.item:id,sku,unit_id', 'items.item.unit:id,symbol', 'customer', 'warehouse:id,name', 'creator:id,name', 'payments')->findOrFail($id);
        $tenant = app('currentTenant');

        return response()->json(['sale' => $sale, 'company' => [
            'name' => $tenant->name, 'phone' => $tenant->phone, 'email' => $tenant->email, 'currency' => $tenant->currency,
        ]]);
    }

    /** POS checkout: creates a completed sale, deducts stock, records payment. */
    public function store(Request $request, InventoryService $inv): JsonResponse
    {
        $tenantId = app('currentTenantId');
        $tenant = app('currentTenant');

        $data = $request->validate([
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'date' => ['nullable', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_method' => ['required', Rule::in(['cash', 'card', 'transfer', 'credit'])],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $sale = DB::transaction(function () use ($data, $request, $inv, $tenant) {
            $warehouseId = (int) ($data['warehouse_id'] ?? $inv->defaultWarehouseId());
            $shift = Shift::where('user_id', $request->user()->id)->where('status', 'open')->first();

            $taxRate = (float) ($data['tax_rate'] ?? $tenant->tax_rate ?? 0);
            $subtotal = 0;
            $lines = [];
            foreach ($data['lines'] as $l) {
                $item = Item::findOrFail($l['item_id']);
                $lineTotal = round((float) $l['qty'] * (float) $l['unit_price'] - (float) ($l['discount'] ?? 0), 3);
                $subtotal += $lineTotal;
                $lines[] = [$item, $l, $lineTotal];
            }
            $discount = (float) ($data['discount'] ?? 0);
            $taxable = max(0, $subtotal - $discount);
            $tax = round($taxable * $taxRate / 100, 3);
            $total = round($taxable + $tax, 3);
            $method = $data['payment_method'];
            $paid = $method === 'credit' ? (float) ($data['paid'] ?? 0) : (float) ($data['paid'] ?? $total);
            $paid = min($paid, $total);

            $sale = Sale::create([
                'number' => Sequence::next('sale', 'INV'),
                'customer_id' => $data['customer_id'] ?? null,
                'warehouse_id' => $warehouseId,
                'shift_id' => $shift?->id,
                'date' => $data['date'] ?? now()->toDateString(),
                'status' => 'completed',
                'subtotal' => $subtotal, 'discount' => $discount, 'tax_rate' => $taxRate, 'tax' => $tax, 'total' => $total,
                'paid' => $paid, 'payment_method' => $method,
                'note' => $data['note'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($lines as [$item, $l, $lineTotal]) {
                SaleItem::create([
                    'sale_id' => $sale->id, 'item_id' => $item->id, 'name' => $item->name,
                    'qty' => $l['qty'], 'unit_price' => $l['unit_price'], 'unit_cost' => $item->cost_price,
                    'discount' => $l['discount'] ?? 0, 'total' => $lineTotal,
                ]);
                $inv->move($item, $warehouseId, -(float) $l['qty'], 'sale', ['ref_type' => 'sale', 'ref_id' => $sale->id, 'unit_cost' => $item->cost_price]);
            }

            if ($paid > 0) {
                SalePayment::create(['sale_id' => $sale->id, 'amount' => $paid, 'method' => $method === 'credit' ? 'cash' : $method, 'date' => $sale->date, 'user_id' => $request->user()->id]);
            }
            app(LedgerService::class)->postSale($sale->fresh()->load('customer'));

            return $sale;
        });

        return response()->json($sale->load('items', 'customer:id,name,phone'), 201);
    }

    /** Cancel a sale: restores stock. */
    public function cancel(int $id, InventoryService $inv): JsonResponse
    {
        $sale = Sale::with('items')->findOrFail($id);
        if ($sale->status === 'cancelled') {
            return response()->json(['message' => 'الفاتورة ملغاة مسبقاً.'], 422);
        }
        DB::transaction(function () use ($sale, $inv) {
            foreach ($sale->items as $line) {
                $inv->move(Item::findOrFail($line->item_id), $sale->warehouse_id, (float) $line->qty, 'sale_cancel', ['ref_type' => 'sale', 'ref_id' => $sale->id]);
            }
            $sale->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $ledger = app(LedgerService::class);
            $ledger->reverse('sale', $sale->id, 'إلغاء فاتورة '.$sale->number);
            foreach ($sale->payments as $p) {
                $ledger->reverse('sale_payment', $p->id, 'إلغاء فاتورة '.$sale->number);
            }
        });

        return response()->json($sale->fresh());
    }

    /** Record a payment against a credit sale. */
    public function pay(Request $request, int $id): JsonResponse
    {
        $sale = Sale::findOrFail($id);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(['cash', 'card', 'transfer'])],
            'date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $remaining = (float) $sale->total - (float) $sale->paid;
        if ($data['amount'] > $remaining + 0.0001) {
            return response()->json(['message' => 'المبلغ أكبر من المتبقي ('.number_format($remaining, 3).').'], 422);
        }
        DB::transaction(function () use ($sale, $data, $request) {
            $p = SalePayment::create(['sale_id' => $sale->id, 'amount' => $data['amount'], 'method' => $data['method'], 'date' => $data['date'] ?? now()->toDateString(), 'note' => $data['note'] ?? null, 'user_id' => $request->user()->id]);
            $sale->update(['paid' => (float) $sale->paid + (float) $data['amount']]);
            app(LedgerService::class)->postSalePayment($p, $sale->load('customer'));
        });

        return response()->json($sale->fresh()->load('payments'));
    }
}
