<?php

namespace App\Http\Controllers\Api\Service;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Item;
use App\Models\Sequence;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderLine;
use App\Services\DocumentCalculator;
use App\Services\InventoryService;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Work orders: intake → work → ready → delivered (invoice + stock + cost). */
class OrderController extends Controller
{
    public const STATUSES = ['received', 'in_progress', 'waiting_parts', 'ready', 'delivered', 'cancelled'];
    private const OPEN = ['received', 'in_progress', 'waiting_parts', 'ready'];

    public function summary(): JsonResponse
    {
        $open = ServiceOrder::whereIn('status', self::OPEN);
        $by = ServiceOrder::whereIn('status', self::OPEN)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status');

        return response()->json([
            'stats' => [
                'open' => (clone $open)->count(), 'by_status' => $by,
                'late' => (clone $open)->whereNotNull('promised_at')->where('promised_at', '<', now())->count(),
                'received_today' => ServiceOrder::whereDate('received_at', today())->count(),
                'delivered_month' => ServiceOrder::where('status', 'delivered')->where('delivered_at', '>=', now()->startOfMonth())->count(),
                'revenue_month' => round((float) Invoice::whereIn('id', ServiceOrder::where('status', 'delivered')->where('delivered_at', '>=', now()->startOfMonth())->whereNotNull('invoice_id')->pluck('invoice_id'))->sum('total'), 3),
            ],
            'ready' => ServiceOrder::with('customer:id,name,phone')->where('status', 'ready')->orderBy('updated_at')->limit(10)->get(),
            'late' => ServiceOrder::with('customer:id,name,phone', 'technician:id,name')->whereIn('status', self::OPEN)->whereNotNull('promised_at')->where('promised_at', '<', now())->orderBy('promised_at')->limit(10)->get(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $q = ServiceOrder::with('customer:id,name,phone', 'technician:id,name')->latest('received_at')->latest('id');
        $status = $request->query('status', 'open');
        if ($status === 'open') {
            $q->whereIn('status', self::OPEN);
        } elseif ($status && $status !== 'all') {
            $q->where('status', $status);
        }
        if ($t = $request->query('technician_id')) {
            $q->where('technician_id', $t);
        }
        if ($request->boolean('late')) {
            $q->whereIn('status', self::OPEN)->whereNotNull('promised_at')->where('promised_at', '<', now());
        }
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('number', 'like', "%$s%")->orWhere('title', 'like', "%$s%")->orWhere('customer_name', 'like', "%$s%")
                ->orWhere('customer_phone', 'like', "%$s%")->orWhere('details', 'like', "%$s%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")));
        }
        $counts = ServiceOrder::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status');

        return response()->json(['page' => $q->paginate($request->boolean('board') ? 200 : 30), 'counts' => $counts]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(ServiceOrder::with('customer', 'technician:id,name,phone', 'lines.item:id,name,track_stock', 'invoice:id,number,total,paid,status', 'creator:id,name')->findOrFail($id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $o = DB::transaction(function () use ($data, $request) {
            $o = ServiceOrder::create($this->attrs($data) + ['number' => Sequence::next('service', 'WO'), 'status' => 'received', 'created_by' => $request->user()->id]);
            $this->syncLines($o, $data['lines'] ?? []);

            return $o;
        });

        return response()->json($o->fresh(), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $o = ServiceOrder::findOrFail($id);
        abort_if(in_array($o->status, ['delivered', 'cancelled'], true), 422, 'لا يمكن تعديل أمر تم تسليمه أو إلغاؤه.');
        $data = $this->validated($request);
        DB::transaction(function () use ($o, $data) {
            $o->update($this->attrs($data));
            $o->lines()->delete();
            $this->syncLines($o, $data['lines'] ?? []);
        });

        return response()->json($o->fresh());
    }

    public function status(Request $request, int $id): JsonResponse
    {
        $o = ServiceOrder::findOrFail($id);
        $d = $request->validate(['status' => ['required', Rule::in(['received', 'in_progress', 'waiting_parts', 'ready', 'cancelled'])]]);
        abort_if(in_array($o->status, ['delivered', 'cancelled'], true), 422, 'هذا الأمر مغلق.');
        $o->update(['status' => $d['status']]);

        return $this->show($id);
    }

    /**
     * Hand the job back to the customer: issue the invoice (posted to the
     * ledger), deduct stocked parts and post their cost, optionally record the
     * payment taken at the counter.
     */
    public function deliver(Request $request, int $id): JsonResponse
    {
        $o = ServiceOrder::with('lines.item', 'customer')->findOrFail($id);
        abort_if(in_array($o->status, ['delivered', 'cancelled'], true), 422, 'هذا الأمر مغلق.');
        $d = $request->validate(['paid' => 'nullable|numeric|min:0', 'method' => 'nullable|in:cash,card,transfer']);

        DB::transaction(function () use ($o, $d, $request) {
            $invoiceId = null;
            if ($o->lines->count()) {
                $tax = (float) (app('currentTenant')->tax_rate ?? 0);
                $lines = $o->lines->map(fn ($l) => ['item_id' => $l->item_id, 'description' => $l->description, 'qty' => (float) $l->qty, 'unit_price' => (float) $l->unit_price])->all();
                $calc = DocumentCalculator::compute($lines, 'amount', (float) $o->discount, $tax);
                $inv = Invoice::create([
                    'number' => Sequence::next('sale', 'INV'), 'status' => 'sent', 'sent_at' => now(), 'customer_id' => $o->customer_id,
                    'date' => now()->toDateString(), 'subject' => $o->title.($o->customer_id ? '' : ($o->customer_name ? ' — '.$o->customer_name : '')),
                    'reference' => $o->number, 'discount_type' => 'amount', 'discount_value' => (float) $o->discount, 'tax_rate' => $tax,
                    'subtotal' => $calc['subtotal'], 'discount' => $calc['discount'], 'tax' => $calc['tax'], 'total' => $calc['total'], 'created_by' => $request->user()->id,
                ]);
                foreach ($calc['lines'] as $l) {
                    InvoiceItem::create(['invoice_id' => $inv->id] + $l);
                }
                $ledger = app(LedgerService::class);
                $ledger->postInvoice($inv->load('customer'));

                // Stocked parts leave the warehouse; their cost goes to COGS.
                $inventory = app(InventoryService::class);
                $wh = $o->warehouse_id ?: $inventory->defaultWarehouseId();
                $cost = 0.0;
                foreach ($o->lines as $l) {
                    if ($l->item && $l->item->track_stock) {
                        $inventory->move($l->item, $wh, -(float) $l->qty, 'service', ['ref_type' => 'service_order', 'ref_id' => $o->id, 'unit_cost' => (float) $l->item->cost_price, 'note' => $o->number]);
                        $cost += (float) $l->qty * (float) $l->item->cost_price;
                    } else {
                        $cost += (float) $l->qty * (float) $l->unit_cost;
                    }
                }
                $ledger->postServiceCost($o, round($cost, 3));

                if ((float) ($d['paid'] ?? 0) > 0) {
                    $amt = min((float) $d['paid'], (float) $inv->total);
                    $p = InvoicePayment::create(['invoice_id' => $inv->id, 'amount' => $amt, 'method' => $d['method'] ?? 'cash', 'date' => now()->toDateString(), 'reference' => $o->number, 'user_id' => $request->user()->id]);
                    $ledger->postInvoicePayment($p, $inv);
                    $paid = (float) $inv->payments()->sum('amount');
                    $inv->update(['paid' => $paid, 'status' => $paid + 0.0005 >= (float) $inv->total ? 'paid' : 'partial', 'paid_at' => $paid + 0.0005 >= (float) $inv->total ? now() : null]);
                }
                $invoiceId = $inv->id;
            }
            $o->update(['status' => 'delivered', 'delivered_at' => now(), 'invoice_id' => $invoiceId]);
        });

        return $this->show($id);
    }

    public function destroy(int $id): JsonResponse
    {
        $o = ServiceOrder::findOrFail($id);
        abort_if($o->status === 'delivered', 422, 'لا يمكن حذف أمر تم تسليمه.');
        $o->delete();

        return response()->json(['ok' => true]);
    }

    /* ------------------------------------------------------------------ */

    private function attrs(array $d): array
    {
        $sub = round(collect($d['lines'] ?? [])->sum(fn ($l) => (float) ($l['qty'] ?? 1) * (float) ($l['unit_price'] ?? 0)), 3);

        return [
            'customer_id' => $d['customer_id'] ?? null, 'customer_name' => $d['customer_name'] ?? null, 'customer_phone' => $d['customer_phone'] ?? null,
            'title' => $d['title'], 'details' => $d['details'] ?? null, 'priority' => $d['priority'] ?? 'normal', 'technician_id' => $d['technician_id'] ?? null,
            'received_at' => $d['received_at'] ?? now(), 'promised_at' => $d['promised_at'] ?? null, 'estimate' => $d['estimate'] ?? 0,
            'discount' => $d['discount'] ?? 0, 'subtotal' => $sub, 'warehouse_id' => $d['warehouse_id'] ?? null,
            'diagnosis' => $d['diagnosis'] ?? null, 'notes' => $d['notes'] ?? null, 'internal_notes' => $d['internal_notes'] ?? null,
        ];
    }

    private function syncLines(ServiceOrder $o, array $lines): void
    {
        foreach (array_values($lines) as $i => $l) {
            $item = ! empty($l['item_id']) ? Item::find($l['item_id']) : null;
            $qty = (float) ($l['qty'] ?? 1);
            $price = (float) ($l['unit_price'] ?? 0);
            ServiceOrderLine::create([
                'order_id' => $o->id, 'kind' => $l['kind'] ?? ($item ? 'part' : 'labor'), 'item_id' => $item?->id, 'catalog_id' => $l['catalog_id'] ?? null,
                'description' => trim((string) ($l['description'] ?? '')) ?: ($item?->name ?? '—'), 'qty' => $qty, 'unit_price' => $price,
                'unit_cost' => (float) ($l['unit_cost'] ?? ($item?->cost_price ?? 0)), 'total' => round($qty * $price, 3), 'sort' => $i,
            ]);
        }
    }

    private function validated(Request $request): array
    {
        $t = app('currentTenantId');
        $d = $request->validate([
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $t)],
            'customer_name' => 'nullable|string|max:150', 'customer_phone' => 'nullable|string|max:30',
            'title' => 'required|string|max:255', 'details' => 'nullable|array',
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'technician_id' => ['nullable', Rule::exists('service_technicians', 'id')->where('tenant_id', $t)],
            'received_at' => 'nullable|date', 'promised_at' => 'nullable|date', 'estimate' => 'nullable|numeric|min:0', 'discount' => 'nullable|numeric|min:0',
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('tenant_id', $t)],
            'diagnosis' => 'nullable|string', 'notes' => 'nullable|string', 'internal_notes' => 'nullable|string',
            'lines' => 'nullable|array', 'lines.*.kind' => 'nullable|in:labor,part', 'lines.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', $t)],
            'lines.*.catalog_id' => 'nullable|integer', 'lines.*.description' => 'nullable|string|max:255',
            'lines.*.qty' => 'required_with:lines|numeric|gt:0', 'lines.*.unit_price' => 'nullable|numeric|min:0', 'lines.*.unit_cost' => 'nullable|numeric|min:0',
        ]);
        abort_if(empty($d['customer_id']) && empty($d['customer_name']), 422, 'اختر العميل أو أدخل اسمه.');

        return $d;
    }
}
