<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Sequence;
use App\Services\DocumentCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** CRM quotes / price offers (core engine). Accepted quotes convert into invoices. */
class QuoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Quote::with('customer:id,name,phone', 'creator:id,name', 'invoice:id,number')->withCount('items')->orderByDesc('date')->orderByDesc('id');
        if ($st = $request->query('status')) {
            $st === 'expired_open'
                ? $q->whereIn('status', ['draft', 'sent'])->whereDate('valid_until', '<', now()->toDateString())
                : $q->where('status', $st);
        }
        if ($c = $request->query('customer_id')) {
            $q->where('customer_id', $c);
        }
        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('number', 'like', "%$s%")->orWhere('subject', 'like', "%$s%")->orWhere('reference', 'like', "%$s%")
                ->orWhereHas('customer', fn ($x) => $x->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")));
        }
        if ($from = $request->query('from')) {
            $q->whereDate('date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('date', '<=', $to);
        }

        $stats = [
            'count' => Quote::count(),
            'open' => Quote::whereIn('status', ['draft', 'sent'])->count(),
            'open_total' => (float) Quote::whereIn('status', ['draft', 'sent'])->sum('total'),
            'accepted_total' => (float) Quote::whereIn('status', ['accepted', 'converted'])->sum('total'),
            'converted' => Quote::where('status', 'converted')->count(),
        ];

        return response()->json(['stats' => $stats] + $q->paginate(30)->toArray());
    }

    public function show(int $id): JsonResponse
    {
        $quote = Quote::with('items.item:id,sku', 'customer', 'creator:id,name', 'invoice:id,number,status')->findOrFail($id);

        return response()->json(['quote' => $quote, 'company' => $this->company()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $quote = DB::transaction(function () use ($data, $request) {
            $calc = DocumentCalculator::compute($data['lines'], $data['discount_type'] ?? 'amount', (float) ($data['discount_value'] ?? 0), (float) ($data['tax_rate'] ?? app('currentTenant')->tax_rate ?? 0));
            $status = $data['status'] ?? 'draft';
            $quote = Quote::create($this->attributes($data, $calc) + [
                'number' => Sequence::next('quote', 'QUO'),
                'status' => $status,
                'created_by' => $request->user()->id,
                'sent_at' => $status === 'sent' ? now() : null,
            ]);
            $this->syncLines($quote, $calc['lines']);

            return $quote;
        });

        return response()->json($quote->load('items', 'customer:id,name'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $quote = Quote::findOrFail($id);
        if (! in_array($quote->status, ['draft', 'sent'], true)) {
            return response()->json(['message' => 'يمكن تعديل العروض المفتوحة (مسودّة/مُرسَلة) فقط.'], 422);
        }
        $data = $this->validated($request);
        DB::transaction(function () use ($quote, $data) {
            $calc = DocumentCalculator::compute($data['lines'], $data['discount_type'] ?? 'amount', (float) ($data['discount_value'] ?? 0), (float) ($data['tax_rate'] ?? 0));
            $attrs = $this->attributes($data, $calc);
            $requested = $data['status'] ?? $quote->status;
            $attrs['status'] = $requested === 'sent' || $quote->status === 'sent' ? 'sent' : 'draft';
            if ($attrs['status'] === 'sent' && ! $quote->sent_at) {
                $attrs['sent_at'] = now();
            }
            $quote->update($attrs);
            $quote->items()->delete();
            $this->syncLines($quote, $calc['lines']);
        });

        return response()->json($quote->fresh()->load('items', 'customer:id,name'));
    }

    public function destroy(int $id): JsonResponse
    {
        $quote = Quote::findOrFail($id);
        if ($quote->status === 'converted') {
            return response()->json(['message' => 'لا يمكن حذف عرض تم تحويله إلى فاتورة.'], 422);
        }
        $quote->delete();

        return response()->json(['ok' => true]);
    }

    /** sent | accepted | rejected | expired | draft (reopen) */
    public function setStatus(Request $request, int $id): JsonResponse
    {
        $quote = Quote::findOrFail($id);
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'sent', 'accepted', 'rejected', 'expired'])]]);
        if ($quote->status === 'converted') {
            return response()->json(['message' => 'العرض محوّل إلى فاتورة ولا يمكن تغيير حالته.'], 422);
        }
        $to = $data['status'];
        $quote->update([
            'status' => $to,
            'sent_at' => $to === 'sent' && ! $quote->sent_at ? now() : $quote->sent_at,
            'accepted_at' => $to === 'accepted' ? now() : ($to === 'draft' ? null : $quote->accepted_at),
        ]);

        return response()->json($quote->fresh());
    }

    /** Create an invoice from this quote (same lines/totals) and mark the quote converted. */
    public function convert(Request $request, int $id): JsonResponse
    {
        $quote = Quote::with('items')->findOrFail($id);
        if ($quote->status === 'converted') {
            return response()->json(['message' => 'العرض محوّل مسبقاً إلى فاتورة '.($quote->invoice?->number ?? '').'.'], 422);
        }
        if ($quote->status === 'rejected') {
            return response()->json(['message' => 'لا يمكن تحويل عرض مرفوض.'], 422);
        }
        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'status' => ['nullable', Rule::in(['draft', 'sent'])],
        ]);

        $inv = DB::transaction(function () use ($quote, $data, $request) {
            $date = $data['date'] ?? now()->toDateString();
            $due = $data['due_date'] ?? (! empty($data['payment_terms_days']) ? \Carbon\Carbon::parse($date)->addDays((int) $data['payment_terms_days'])->toDateString() : null);
            $status = $data['status'] ?? 'draft';
            $inv = Invoice::create($quote->only(['customer_id', 'subject', 'reference', 'subtotal', 'discount_type', 'discount_value', 'discount', 'tax_rate', 'tax', 'total', 'notes', 'internal_notes']) + [
                'number' => Sequence::next('sale', 'INV'), 'quote_id' => $quote->id,
                'date' => $date, 'due_date' => $due, 'payment_terms_days' => $data['payment_terms_days'] ?? null,
                'status' => $status, 'paid' => 0, 'created_by' => $request->user()->id, 'sent_at' => $status === 'sent' ? now() : null,
            ]);
            foreach ($quote->items as $l) {
                InvoiceItem::create(['invoice_id' => $inv->id] + $l->only(['item_id', 'description', 'qty', 'unit', 'unit_price', 'discount', 'total', 'sort']));
            }
            $quote->update(['status' => 'converted', 'converted_invoice_id' => $inv->id, 'accepted_at' => $quote->accepted_at ?: now()]);

            return $inv;
        });

        return response()->json(['invoice' => $inv, 'quote' => $quote->fresh()], 201);
    }

    /* ---------------- helpers ---------------- */

    private function validated(Request $request): array
    {
        $tenantId = app('currentTenantId');

        return $request->validate([
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:date'],
            'status' => ['nullable', Rule::in(['draft', 'sent'])],
            'subject' => ['nullable', 'string', 'max:200'],
            'reference' => ['nullable', 'string', 'max:80'],
            'discount_type' => ['nullable', Rule::in(['amount', 'percent'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'terms' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('tenant_id', $tenantId)],
            'lines.*.description' => ['required_without:lines.*.item_id', 'nullable', 'string', 'max:255'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit' => ['nullable', 'string', 'max:20'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function attributes(array $data, array $calc): array
    {
        return [
            'customer_id' => $data['customer_id'] ?? null,
            'date' => $data['date'], 'valid_until' => $data['valid_until'] ?? null,
            'subject' => $data['subject'] ?? null, 'reference' => $data['reference'] ?? null,
            'discount_type' => $data['discount_type'] ?? 'amount', 'discount_value' => $data['discount_value'] ?? 0,
            'tax_rate' => $data['tax_rate'] ?? 0,
            'subtotal' => $calc['subtotal'], 'discount' => $calc['discount'], 'tax' => $calc['tax'], 'total' => $calc['total'],
            'terms' => $data['terms'] ?? null, 'notes' => $data['notes'] ?? null, 'internal_notes' => $data['internal_notes'] ?? null,
        ];
    }

    private function syncLines(Quote $quote, array $lines): void
    {
        foreach ($lines as $l) {
            QuoteItem::create(['quote_id' => $quote->id] + $l);
        }
    }

    private function company(): array
    {
        $t = app('currentTenant');
        $s = (array) ($t->settings ?? []);

        return [
            'name' => $t->name, 'phone' => $t->phone, 'email' => $t->email, 'currency' => $t->currency, 'logo' => $t->logo,
            'address' => $s['address'] ?? null, 'tax_number' => $s['tax_number'] ?? null, 'cr_number' => $s['cr_number'] ?? null,
            'bank_details' => $s['bank_details'] ?? null, 'invoice_terms' => $s['invoice_terms'] ?? null, 'invoice_footer' => $s['invoice_footer'] ?? null,
        ];
    }
}
