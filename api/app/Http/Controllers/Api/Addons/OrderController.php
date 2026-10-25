<?php

namespace App\Http\Controllers\Api\Addons;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderPayment;
use App\Models\Sequence;
use App\Services\DocumentCalculator;
use App\Services\InventoryService;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Add-on «الطلبات المسبقة والتوصيل».
 *
 *  - An order is taken for a date/time (pickup or delivery) and may get deposits:
 *    Dr cash/bank, Cr 2600 customer_advances (money held, not yet revenue).
 *  - It moves on a board: new → confirmed → preparing → ready → (out) → delivered.
 *  - Handing it over issues the invoice (Dr AR / Cr sales + VAT), takes stocked
 *    items out of the warehouse (Dr COGS / Cr inventory) and applies the deposits
 *    (Dr customer_advances / Cr AR); the rest can be collected on the spot.
 *  - Cancelling may give the deposits back (Dr customer_advances / Cr cash).
 */
class OrderController extends Controller
{
    private const WITH = ['customer:id,name,phone,address', 'creator:id,name'];

    public function summary(): JsonResponse
    {
        $open = Order::whereNotIn('status', ['delivered', 'cancelled']);

        return response()->json([
            'by_status' => (clone $open)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'today' => (clone $open)->whereDate('due_at', today())->count(),
            'tomorrow' => (clone $open)->whereDate('due_at', today()->addDay())->count(),
            'late' => (clone $open)->where('due_at', '<', now())->count(),
            'deposits_held' => (float) (clone $open)->sum('paid'),
            'open_value' => (float) (clone $open)->sum('total'),
            'delivered_today' => Order::where('status', 'delivered')->whereDate('delivered_at', today())->count(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $q = Order::with(self::WITH)->withCount('lines');
        $scope = $request->query('scope', 'open');
        match ($scope) {
            'today' => $q->whereNotIn('status', ['delivered', 'cancelled'])->whereDate('due_at', today()),
            'tomorrow' => $q->whereNotIn('status', ['delivered', 'cancelled'])->whereDate('due_at', today()->addDay()),
            'week' => $q->whereNotIn('status', ['delivered', 'cancelled'])->whereBetween('due_at', [today(), today()->addDays(7)->endOfDay()]),
            'late' => $q->whereNotIn('status', ['delivered', 'cancelled'])->where('due_at', '<', now()),
            'delivered' => $q->where('status', 'delivered'),
            'cancelled' => $q->where('status', 'cancelled'),
            'all' => null,
            default => $q->whereNotIn('status', ['delivered', 'cancelled']),
        };
        if ($st = $request->query('status')) {
            $q->where('status', $st);
        }
        if ($d = $request->query('date')) {
            $q->whereDate('due_at', $d);
        }
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('number', 'like', "%$s%")->orWhere('customer_name', 'like', "%$s%")->orWhere('customer_phone', 'like', "%$s%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")));
        }
        in_array($scope, ['delivered', 'cancelled'], true) ? $q->orderByDesc('due_at') : $q->orderBy('due_at');

        // The board shows every open order at once (with its lines for the preparation view).
        if ($request->boolean('board')) {
            return response()->json($q->with('lines:id,order_id,description,qty,unit')->limit(300)->get());
        }

        return response()->json($q->paginate(25));
    }

    public function show(int $id): JsonResponse
    {
        $o = Order::with(array_merge(self::WITH, ['lines', 'payments.user:id,name', 'invoice:id,number,status,total,paid']))->findOrFail($id);
        $tenant = app('currentTenant');

        return response()->json(['order' => $o, 'company' => ['name' => $tenant->name, 'phone' => $tenant->phone, 'address' => ($tenant->settings ?? [])['address'] ?? null]]);
    }

    private function rules(): array
    {
        $tid = app('currentTenantId');

        return [
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tid)],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'channel' => ['nullable', Rule::in(['shop', 'phone', 'whatsapp', 'online'])],
            'fulfilment' => ['required', Rule::in(['pickup', 'delivery'])],
            'due_at' => ['required', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'driver' => ['nullable', 'string', 'max:120'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', Rule::in(['amount', 'percent'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('tenant_id', $tid)],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', $tid)],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.unit' => ['nullable', 'string', 'max:30'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    private function fill(Order $o, array $d): void
    {
        if (empty($d['customer_id']) && empty($d['customer_name'])) {
            throw ValidationException::withMessages(['customer_name' => 'اختر العميل أو اكتب اسم صاحب الطلب.']);
        }
        if ($d['fulfilment'] === 'delivery' && empty($d['address'])) {
            throw ValidationException::withMessages(['address' => 'اكتب عنوان التوصيل.']);
        }
        $lines = array_values(array_filter($d['lines'], fn ($l) => ! empty($l['item_id']) || trim((string) ($l['description'] ?? '')) !== ''));
        if (! $lines) {
            throw ValidationException::withMessages(['lines' => 'أضف صنفاً واحداً على الأقل للطلب.']);
        }
        $fee = $d['fulfilment'] === 'delivery' ? (float) ($d['delivery_fee'] ?? 0) : 0;
        $tax = (float) (app('currentTenant')->tax_rate ?? 0);
        $calc = DocumentCalculator::compute($lines, $d['discount_type'] ?? 'amount', (float) ($d['discount_value'] ?? 0), $tax);
        // The delivery fee is part of the bill (taxed like the goods).
        $feeTax = round($fee * $tax / 100, 3);

        $o->fill([
            'customer_id' => $d['customer_id'] ?? null, 'customer_name' => $d['customer_name'] ?? null, 'customer_phone' => $d['customer_phone'] ?? null,
            'channel' => $d['channel'] ?? 'shop', 'fulfilment' => $d['fulfilment'], 'due_at' => $d['due_at'],
            'address' => $d['fulfilment'] === 'delivery' ? ($d['address'] ?? null) : null, 'driver' => $d['driver'] ?? null,
            'delivery_fee' => $fee, 'notes' => $d['notes'] ?? null, 'warehouse_id' => $d['warehouse_id'] ?? $o->warehouse_id,
            'subtotal' => $calc['subtotal'], 'discount' => $calc['discount'], 'tax_rate' => $tax,
            'tax' => round($calc['tax'] + $feeTax, 3), 'total' => round($calc['total'] + $fee + $feeTax, 3),
        ])->save();
        $o->lines()->delete();
        foreach ($calc['lines'] as $l) {
            OrderLine::create(['order_id' => $o->id] + $l);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $d = $request->validate($this->rules() + ['deposit' => ['nullable', 'numeric', 'min:0'], 'deposit_method' => ['nullable', Rule::in(['cash', 'card', 'transfer'])]]);
        $o = DB::transaction(function () use ($d, $request) {
            $o = new Order(['number' => Sequence::next('order', 'ORD'), 'status' => 'new', 'created_by' => $request->user()->id]);
            $this->fill($o, $d);
            if ((float) ($d['deposit'] ?? 0) > 0) {
                $this->addPayment($o, 'deposit', (float) $d['deposit'], $d['deposit_method'] ?? 'cash', 'عربون عند الطلب');
            }

            return $o;
        });

        return $this->show($o->id)->setStatusCode(201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $o = Order::findOrFail($id);
        abort_unless($o->isOpen(), 422, 'هذا الطلب مُسلَّم أو ملغى ولا يمكن تعديله.');
        $d = $request->validate($this->rules());
        DB::transaction(fn () => $this->fill($o, $d));

        return $this->show($id);
    }

    /** Move along the board (any open step; «delivered» goes through deliver()). */
    public function status(Request $request, int $id): JsonResponse
    {
        $o = Order::findOrFail($id);
        abort_unless($o->isOpen(), 422, 'هذا الطلب مغلق.');
        $d = $request->validate(['status' => ['required', Rule::in(['new', 'confirmed', 'preparing', 'ready', 'out'])], 'driver' => ['nullable', 'string', 'max:120']]);
        abort_if($d['status'] === 'out' && $o->fulfilment !== 'delivery', 422, 'هذا الطلب استلام من المحل وليس توصيلاً.');
        $o->update(['status' => $d['status'], 'ready_at' => $d['status'] === 'ready' ? now() : $o->ready_at, 'driver' => $d['driver'] ?? $o->driver]);

        return $this->show($id);
    }

    public function deposit(Request $request, int $id): JsonResponse
    {
        $o = Order::findOrFail($id);
        abort_unless($o->isOpen(), 422, 'هذا الطلب مغلق.');
        $d = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'method' => ['required', Rule::in(['cash', 'card', 'transfer'])], 'note' => ['nullable', 'string', 'max:255']]);
        DB::transaction(fn () => $this->addPayment($o, 'deposit', (float) $d['amount'], $d['method'], $d['note'] ?? null));

        return $this->show($id);
    }

    private function addPayment(Order $o, string $kind, float $amount, string $method, ?string $note): void
    {
        $p = OrderPayment::create(['order_id' => $o->id, 'kind' => $kind, 'amount' => $amount, 'method' => $method, 'date' => now()->toDateString(), 'note' => $note, 'user_id' => auth()->id()]);
        $ledger = app(LedgerService::class);
        $name = $o->customer?->name ?: $o->customer_name;
        $lines = [
            ['account' => $ledger->accountForMethod($method), 'debit' => $amount],
            ['account' => 'customer_advances', 'credit' => $amount, 'description' => $name],
        ];
        if ($kind === 'refund') {
            $lines = [['account' => 'customer_advances', 'debit' => $amount, 'description' => $name], ['account' => $ledger->accountForMethod($method), 'credit' => $amount]];
        }
        $ledger->post($lines, ['date' => $p->date->toDateString(), 'description' => ($kind === 'refund' ? 'رد عربون الطلب ' : 'عربون الطلب ').$o->number, 'reference' => $o->number, 'source_type' => 'order_payment', 'source_id' => $p->id]);
        $o->update(['paid' => round((float) $o->paid + ($kind === 'refund' ? -$amount : $amount), 3)]);
    }

    /** Hand over: invoice + stock out + deposits applied (+ the rest collected now). */
    public function deliver(Request $request, int $id, InventoryService $inventory): JsonResponse
    {
        $o = Order::with('lines.item', 'customer')->findOrFail($id);
        abort_unless($o->isOpen(), 422, 'هذا الطلب مغلق.');
        $d = $request->validate(['paid' => ['nullable', 'numeric', 'min:0'], 'method' => ['nullable', Rule::in(['cash', 'card', 'transfer'])]]);

        DB::transaction(function () use ($o, $d, $request, $inventory) {
            $ledger = app(LedgerService::class);
            $inv = Invoice::create([
                'number' => Sequence::next('sale', 'INV'), 'status' => 'sent', 'sent_at' => now(), 'customer_id' => $o->customer_id,
                'date' => now()->toDateString(), 'subject' => 'طلب '.$o->number.($o->customer_id ? '' : ($o->customer_name ? ' — '.$o->customer_name : '')),
                'reference' => $o->number, 'discount_type' => 'amount', 'discount_value' => (float) $o->discount, 'tax_rate' => (float) $o->tax_rate,
                'subtotal' => round((float) $o->subtotal + (float) $o->delivery_fee, 3), 'discount' => (float) $o->discount, 'tax' => (float) $o->tax, 'total' => (float) $o->total,
                'created_by' => $request->user()->id,
            ]);
            $sort = 0;
            foreach ($o->lines as $l) {
                InvoiceItem::create(['invoice_id' => $inv->id, 'item_id' => $l->item_id, 'description' => $l->description, 'unit' => $l->unit, 'qty' => $l->qty, 'unit_price' => $l->unit_price, 'discount' => $l->discount, 'total' => $l->total, 'sort' => $sort++]);
            }
            if ((float) $o->delivery_fee > 0) {
                InvoiceItem::create(['invoice_id' => $inv->id, 'item_id' => null, 'description' => 'رسوم التوصيل', 'qty' => 1, 'unit_price' => $o->delivery_fee, 'discount' => 0, 'total' => $o->delivery_fee, 'sort' => $sort]);
            }
            $ledger->postInvoice($inv->load('customer'));

            // Stocked items leave the warehouse; their cost goes to COGS.
            $wh = $o->warehouse_id ?: $inventory->defaultWarehouseId();
            $cost = 0.0;
            foreach ($o->lines as $l) {
                if ($l->item && $l->item->track_stock) {
                    $inventory->move($l->item, $wh, -(float) $l->qty, 'sale', ['ref_type' => 'order', 'ref_id' => $o->id, 'unit_cost' => (float) $l->item->cost_price, 'note' => $o->number]);
                    $cost += (float) $l->qty * (float) $l->item->cost_price;
                }
            }
            if ($cost > 0) {
                $ledger->post([['account' => 'cogs', 'debit' => round($cost, 3)], ['account' => 'inventory', 'credit' => round($cost, 3)]],
                    ['date' => now()->toDateString(), 'description' => 'تكلفة الطلب '.$o->number, 'reference' => $o->number, 'source_type' => 'order_cost', 'source_id' => $o->id]);
            }

            // Deposits held become payment of the invoice; then whatever is paid now.
            $payments = [];
            $advance = min((float) $o->paid, (float) $inv->total);
            if ($advance > 0) {
                $payments[] = ['amount' => $advance, 'method' => 'advance'];
            }
            $now = min((float) ($d['paid'] ?? 0), max(0, (float) $inv->total - $advance));
            if ($now > 0) {
                $payments[] = ['amount' => $now, 'method' => $d['method'] ?? 'cash'];
            }
            foreach ($payments as $pm) {
                $p = InvoicePayment::create(['invoice_id' => $inv->id, 'amount' => $pm['amount'], 'method' => $pm['method'], 'date' => now()->toDateString(), 'reference' => $o->number, 'user_id' => $request->user()->id]);
                $ledger->postInvoicePayment($p, $inv);
            }
            $paid = (float) $inv->payments()->sum('amount');
            $full = $paid + 0.0005 >= (float) $inv->total;
            $inv->update(['paid' => $paid, 'status' => $full ? 'paid' : ($paid > 0 ? 'partial' : 'sent'), 'paid_at' => $full ? now() : null]);
            $o->update(['status' => 'delivered', 'delivered_at' => now(), 'invoice_id' => $inv->id, 'paid' => round((float) $o->paid - $advance, 3)]);
        });

        return $this->show($id);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $o = Order::findOrFail($id);
        abort_unless($o->isOpen(), 422, 'هذا الطلب مغلق.');
        $d = $request->validate(['reason' => ['nullable', 'string', 'max:255'], 'refund' => ['nullable', 'boolean'], 'method' => ['nullable', Rule::in(['cash', 'card', 'transfer'])]]);
        DB::transaction(function () use ($o, $d) {
            if (($d['refund'] ?? false) && (float) $o->paid > 0) {
                $this->addPayment($o, 'refund', (float) $o->paid, $d['method'] ?? 'cash', 'رد العربون عند الإلغاء');
            } elseif ((float) $o->paid > 0) {
                // Deposit kept (not refunded): it is no longer owed to the customer → other income.
                app(LedgerService::class)->post([
                    ['account' => 'customer_advances', 'debit' => (float) $o->paid, 'description' => $o->customer?->name ?: $o->customer_name],
                    ['account' => 'other_income', 'credit' => (float) $o->paid],
                ], ['date' => now()->toDateString(), 'description' => 'عربون غير مسترد — إلغاء الطلب '.$o->number, 'reference' => $o->number, 'source_type' => 'order_forfeit', 'source_id' => $o->id]);
            }
            $o->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $d['reason'] ?? null]);
        });

        return $this->show($id);
    }
}
