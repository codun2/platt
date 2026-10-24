<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Sequence;
use App\Services\DocumentCalculator;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** CRM sales invoices (core engine) — lines may be inventory items or free text. */
class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Invoice::with('customer:id,name,phone', 'creator:id,name')->withCount('items')->orderByDesc('date')->orderByDesc('id');
        if ($st = $request->query('status')) {
            $st === 'overdue'
                ? $q->whereIn('status', ['sent', 'partial'])->whereDate('due_date', '<', now()->toDateString())
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
        if ($request->boolean('unpaid')) {
            $q->whereIn('status', ['sent', 'partial']);
        }

        $open = Invoice::whereIn('status', ['sent', 'partial']);
        $stats = [
            'count' => Invoice::where('status', '!=', 'cancelled')->count(),
            'total' => (float) Invoice::where('status', '!=', 'cancelled')->sum('total'),
            'collected' => (float) Invoice::where('status', '!=', 'cancelled')->sum('paid'),
            'outstanding' => (float) (clone $open)->selectRaw('coalesce(sum(total - paid),0) as d')->value('d'),
            'overdue' => (float) (clone $open)->whereDate('due_date', '<', now()->toDateString())->selectRaw('coalesce(sum(total - paid),0) as d')->value('d'),
            'overdue_count' => (clone $open)->whereDate('due_date', '<', now()->toDateString())->count(),
        ];

        return response()->json(['stats' => $stats] + $q->paginate(30)->toArray());
    }

    public function show(int $id): JsonResponse
    {
        $inv = Invoice::with('items.item:id,sku', 'customer', 'quote:id,number', 'creator:id,name', 'payments.user:id,name')->findOrFail($id);

        return response()->json(['invoice' => $inv, 'company' => $this->company()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $inv = DB::transaction(function () use ($data, $request) {
            $calc = DocumentCalculator::compute($data['lines'], $data['discount_type'] ?? 'amount', (float) ($data['discount_value'] ?? 0), (float) ($data['tax_rate'] ?? app('currentTenant')->tax_rate ?? 0));
            $status = $data['status'] ?? 'draft';
            $inv = Invoice::create($this->attributes($data, $calc) + [
                'number' => Sequence::next('sale', 'INV'),
                'status' => $status,
                'created_by' => $request->user()->id,
                'sent_at' => $status === 'sent' ? now() : null,
            ]);
            $this->syncLines($inv, $calc['lines']);
            if ($status === 'sent') {
                app(LedgerService::class)->postInvoice($inv->load('customer'));
            }

            return $inv;
        });

        return response()->json($inv->load('items', 'customer:id,name'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $inv = Invoice::findOrFail($id);
        if (in_array($inv->status, ['paid', 'cancelled'], true)) {
            return response()->json(['message' => 'لا يمكن تعديل فاتورة مسدّدة أو ملغاة.'], 422);
        }
        $data = $this->validated($request);
        DB::transaction(function () use ($inv, $data) {
            $calc = DocumentCalculator::compute($data['lines'], $data['discount_type'] ?? 'amount', (float) ($data['discount_value'] ?? 0), (float) ($data['tax_rate'] ?? 0));
            if ((float) $inv->paid > $calc['total'] + 0.0005) {
                abort(422, 'الإجمالي الجديد أقل من المبلغ المدفوع مسبقاً ('.number_format((float) $inv->paid, 3).').');
            }
            $attrs = $this->attributes($data, $calc);
            $requested = $data['status'] ?? $inv->status;
            // Keep payment-derived states consistent with the new total.
            $attrs['status'] = $this->deriveStatus($inv, $calc['total'], $requested);
            if ($attrs['status'] === 'sent' && ! $inv->sent_at) {
                $attrs['sent_at'] = now();
            }
            $inv->update($attrs);
            $inv->items()->delete();
            $this->syncLines($inv, $calc['lines']);
            if ($attrs['status'] !== 'draft') {   // re-post the issue entry with the new totals
                $ledger = app(LedgerService::class);
                $ledger->reverse('invoice', $inv->id, 'تعديل فاتورة '.$inv->number);
                $ledger->postInvoice($inv->fresh()->load('customer'));
            }
        });

        return response()->json($inv->fresh()->load('items', 'customer:id,name'));
    }

    public function destroy(int $id): JsonResponse
    {
        $inv = Invoice::findOrFail($id);
        if ($inv->status !== 'draft') {
            return response()->json(['message' => 'يمكن حذف المسودّات فقط — ألغِ الفاتورة بدلاً من ذلك.'], 422);
        }
        $inv->delete();

        return response()->json(['ok' => true]);
    }

    public function markSent(int $id): JsonResponse
    {
        $inv = Invoice::findOrFail($id);
        if ($inv->status !== 'draft') {
            return response()->json(['message' => 'الفاتورة ليست مسودّة.'], 422);
        }
        $inv->update(['status' => 'sent', 'sent_at' => now()]);
        app(LedgerService::class)->postInvoice($inv->fresh()->load('customer'));

        return response()->json($inv->fresh());
    }

    public function cancel(int $id): JsonResponse
    {
        $inv = Invoice::findOrFail($id);
        if ($inv->status === 'cancelled') {
            return response()->json(['message' => 'الفاتورة ملغاة مسبقاً.'], 422);
        }
        if ((float) $inv->paid > 0) {
            return response()->json(['message' => 'لا يمكن إلغاء فاتورة عليها دفعات — احذف الدفعات أولاً.'], 422);
        }
        $inv->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        app(LedgerService::class)->reverse('invoice', $inv->id, 'إلغاء فاتورة '.$inv->number);

        return response()->json($inv->fresh());
    }

    /** Copy an invoice into a new draft (same lines, today's date). */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        $src = Invoice::with('items')->findOrFail($id);
        $copy = DB::transaction(function () use ($src, $request) {
            $copy = Invoice::create($src->only(['customer_id', 'subject', 'reference', 'subtotal', 'discount_type', 'discount_value', 'discount', 'tax_rate', 'tax', 'total', 'payment_terms_days', 'notes', 'internal_notes']) + [
                'number' => Sequence::next('sale', 'INV'), 'date' => now()->toDateString(),
                'due_date' => $src->payment_terms_days ? now()->addDays($src->payment_terms_days)->toDateString() : null,
                'status' => 'draft', 'paid' => 0, 'created_by' => $request->user()->id,
            ]);
            foreach ($src->items as $l) {
                InvoiceItem::create(['invoice_id' => $copy->id] + $l->only(['item_id', 'description', 'qty', 'unit', 'unit_price', 'discount', 'total', 'sort']));
            }

            return $copy;
        });

        return response()->json($copy, 201);
    }

    public function pay(Request $request, int $id): JsonResponse
    {
        $inv = Invoice::findOrFail($id);
        if (! in_array($inv->status, ['sent', 'partial', 'draft'], true)) {
            return response()->json(['message' => 'لا يمكن تسجيل دفعة على هذه الفاتورة.'], 422);
        }
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(InvoicePayment::METHODS)],
            'date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        if ($data['amount'] > $inv->balance + 0.0005) {
            return response()->json(['message' => 'المبلغ أكبر من المتبقي ('.number_format($inv->balance, 3).').'], 422);
        }
        DB::transaction(function () use ($inv, $data, $request) {
            $p = InvoicePayment::create($data + ['invoice_id' => $inv->id, 'date' => $data['date'] ?? now()->toDateString(), 'user_id' => $request->user()->id]);
            $this->refreshPaid($inv);
            $ledger = app(LedgerService::class);
            $ledger->postInvoice($inv->fresh()->load('customer'));   // a draft paid directly is issued implicitly
            $ledger->postInvoicePayment($p, $inv->load('customer'));
        });

        return response()->json($inv->fresh()->load('payments.user:id,name'));
    }

    public function deletePayment(int $id, int $paymentId): JsonResponse
    {
        $inv = Invoice::findOrFail($id);
        DB::transaction(function () use ($inv, $paymentId) {
            $p = InvoicePayment::where('invoice_id', $inv->id)->findOrFail($paymentId);
            app(LedgerService::class)->reverse('invoice_payment', $p->id, 'حذف دفعة فاتورة '.$inv->number);
            $p->delete();
            $this->refreshPaid($inv);
        });

        return response()->json($inv->fresh()->load('payments.user:id,name'));
    }

    /* ---------------- helpers ---------------- */

    private function validated(Request $request): array
    {
        $tenantId = app('currentTenantId');

        return $request->validate([
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'student_id' => ['nullable', Rule::exists('edu_students', 'id')->where('tenant_id', $tenantId)],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'status' => ['nullable', Rule::in(['draft', 'sent'])],
            'subject' => ['nullable', 'string', 'max:200'],
            'reference' => ['nullable', 'string', 'max:80'],
            'discount_type' => ['nullable', Rule::in(['amount', 'percent'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
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
        $due = $data['due_date'] ?? null;
        if (! $due && ! empty($data['payment_terms_days'])) {
            $due = \Carbon\Carbon::parse($data['date'])->addDays((int) $data['payment_terms_days'])->toDateString();
        }

        return [
            'customer_id' => $data['customer_id'] ?? null,
            'student_id' => $data['student_id'] ?? null,
            'date' => $data['date'], 'due_date' => $due,
            'payment_terms_days' => $data['payment_terms_days'] ?? null,
            'subject' => $data['subject'] ?? null, 'reference' => $data['reference'] ?? null,
            'discount_type' => $data['discount_type'] ?? 'amount', 'discount_value' => $data['discount_value'] ?? 0,
            'tax_rate' => $data['tax_rate'] ?? 0,
            'subtotal' => $calc['subtotal'], 'discount' => $calc['discount'], 'tax' => $calc['tax'], 'total' => $calc['total'],
            'notes' => $data['notes'] ?? null, 'internal_notes' => $data['internal_notes'] ?? null,
        ];
    }

    private function syncLines(Invoice $inv, array $lines): void
    {
        foreach ($lines as $l) {
            InvoiceItem::create(['invoice_id' => $inv->id] + $l);
        }
    }

    private function deriveStatus(Invoice $inv, float $total, string $requested): string
    {
        $paid = (float) $inv->paid;
        if ($paid <= 0) {
            // No money yet: a draft stays a draft unless explicitly sent; anything else stays "sent".
            return ($requested === 'sent' || $inv->status !== 'draft') ? 'sent' : 'draft';
        }

        return $paid + 0.0005 >= $total ? 'paid' : 'partial';
    }

    private function refreshPaid(Invoice $inv): void
    {
        $paid = (float) $inv->payments()->sum('amount');
        $status = $paid <= 0 ? ($inv->status === 'draft' ? 'draft' : 'sent') : ($paid + 0.0005 >= (float) $inv->total ? 'paid' : 'partial');
        $inv->update(['paid' => $paid, 'status' => $status, 'paid_at' => $status === 'paid' ? now() : null, 'sent_at' => $inv->sent_at ?: ($status !== 'draft' ? now() : null)]);
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
