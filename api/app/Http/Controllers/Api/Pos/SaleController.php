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
            // Offline sales (uploaded later by the device's outbox)
            'client_ref' => ['nullable', 'string', 'max:60'],
            'offline' => ['nullable', 'boolean'],
            'sold_at' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Same device sale uploaded twice → the existing sale (idempotent by client_ref).
        if (! empty($data['client_ref']) && ($existing = Sale::where('client_ref', $data['client_ref'])->first())) {
            return response()->json($existing->load('items', 'customer:id,name,phone'), 200);
        }
        $offline = (bool) ($data['offline'] ?? false);
        $soldAt = ! empty($data['sold_at']) ? \Carbon\Carbon::parse($data['sold_at'])->setTimezone(config('app.timezone')) : now();

        $sale = DB::transaction(function () use ($data, $request, $inv, $tenant, $offline, $soldAt) {
            $warehouseId = (int) ($data['warehouse_id'] ?? $inv->defaultWarehouseId());
            // An offline sale belongs to the shift that was open when it happened.
            $shift = $offline
                ? (Shift::where('user_id', $request->user()->id)->where('opened_at', '<=', $soldAt)
                    ->where(fn ($q) => $q->whereNull('closed_at')->orWhere('closed_at', '>=', $soldAt))->latest('opened_at')->first()
                    ?? Shift::where('user_id', $request->user()->id)->where('status', 'open')->first())
                : Shift::where('user_id', $request->user()->id)->where('status', 'open')->first();
            $short = false;

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
                'client_ref' => $data['client_ref'] ?? null, 'offline' => $offline, 'sold_at' => $soldAt,
                'customer_id' => $data['customer_id'] ?? null,
                'warehouse_id' => $warehouseId,
                'shift_id' => $shift?->id,
                'date' => $data['date'] ?? $soldAt->toDateString(),
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
                // The goods already left the shop: an offline sale is never refused for
                // stock — it is recorded and flagged for review instead.
                if ($offline && $item->track_stock && $inv->qty($item, $warehouseId) + 0.0005 < (float) $l['qty']) {
                    $short = true;
                }
                $inv->move($item, $warehouseId, -(float) $l['qty'], 'sale', ['ref_type' => 'sale', 'ref_id' => $sale->id, 'unit_cost' => $item->cost_price, 'allow_negative' => $offline]);
            }

            if ($paid > 0) {
                SalePayment::create(['sale_id' => $sale->id, 'amount' => $paid, 'method' => $method === 'credit' ? 'cash' : $method, 'date' => $sale->date, 'user_id' => $request->user()->id]);
            }
            if ($short) {
                $sale->update(['stock_short' => true]);
            }
            app(LedgerService::class)->postSale($sale->fresh()->load('customer'));

            return $sale;
        });

        return response()->json($sale->load('items', 'customer:id,name,phone'), 201);
    }

    /**
     * Everything the checkout screen needs to keep selling without internet:
     * the full active catalog with stock in the warehouse, customers, categories,
     * the open shift and the tax rate. The device stores it locally.
     */
    public function offlinePack(Request $request, InventoryService $inv): JsonResponse
    {
        $wh = (int) ($request->query('warehouse_id') ?: $inv->defaultWarehouseId());
        $items = Item::with('unit:id,name,symbol')->where('is_active', true)
            ->select(['id', 'name', 'sku', 'barcode', 'category_id', 'unit_id', 'sale_price', 'track_stock'])
            ->withSum(['stockLevels as stock' => fn ($x) => $x->where('warehouse_id', $wh)], 'qty')
            ->orderBy('name')->get();

        return response()->json([
            'generated_at' => now()->toIso8601String(), 'warehouse_id' => $wh,
            'items' => $items, 'customers' => \App\Models\Customer::where('is_active', true)->orderBy('name')->get(['id', 'name', 'phone']),
            'categories' => \App\Models\Category::orderBy('name')->get(['id', 'name']),
            'shift' => Shift::where('user_id', $request->user()->id)->where('status', 'open')->first(['id', 'opened_at', 'warehouse_id', 'opening_cash']),
            'tax_rate' => (float) (app('currentTenant')->tax_rate ?? 0),
        ]);
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
