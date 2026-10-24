<?php

namespace App\Http\Controllers\Api\Education;

use App\Http\Controllers\Controller;
use App\Models\EduClass;
use App\Models\EduStudent;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Sequence;
use App\Services\DocumentCalculator;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Tuition: billed as invoices to the guardian, one per student per installment. */
class FeeController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $q = Invoice::whereNotNull('student_id')->whereNotIn('status', ['cancelled', 'draft']);
        if ($c = $request->query('class_id')) {
            $q->whereHas('student', fn ($s) => $s->where('class_id', $c));
        }
        $billed = round((float) (clone $q)->sum('total'), 3);
        $paid = round((float) (clone $q)->sum('paid'), 3);
        $overdue = round((float) (clone $q)->whereIn('status', ['sent', 'partial'])->whereNotNull('due_date')->where('due_date', '<', now()->toDateString())->sum(DB::raw('total - paid')), 3);
        $list = (clone $q)->with('student:id,name,class_id', 'student.eduClass:id,name', 'customer:id,name,phone')
            ->whereIn('status', ['sent', 'partial'])->orderBy('due_date')->limit(200)->get(['id', 'number', 'student_id', 'customer_id', 'subject', 'date', 'due_date', 'total', 'paid', 'status']);

        return response()->json(['stats' => ['billed' => $billed, 'paid' => $paid, 'balance' => round($billed - $paid, 3), 'overdue' => $overdue,
            'rate' => $billed > 0 ? round($paid * 100 / $billed) : 0], 'open' => $list]);
    }

    /**
     * Issue tuition invoices for every active student of the selected classes.
     * installments: [{label, due_date, percent}] of each class's annual fee
     * (or a fixed amount). Student discount_pct applies. Students without a
     * guardian are skipped and reported.
     */
    public function generate(Request $request): JsonResponse
    {
        $t = app('currentTenantId');
        $d = $request->validate([
            'class_ids' => 'required|array|min:1', 'class_ids.*' => ['integer', Rule::exists('edu_classes', 'id')->where('tenant_id', $t)],
            'installments' => 'required|array|min:1', 'installments.*.label' => 'required|string|max:120', 'installments.*.due_date' => 'required|date',
            'installments.*.percent' => 'nullable|numeric|min:0|max:100', 'installments.*.amount' => 'nullable|numeric|min:0',
            'academic_year' => 'nullable|string|max:20', 'send' => 'boolean',
        ]);
        $send = $d['send'] ?? true;
        $made = 0; $skipped = []; $dupes = 0;
        DB::transaction(function () use ($d, $send, $request, &$made, &$skipped, &$dupes) {
            $ledger = app(LedgerService::class);
            $tax = (float) (app('currentTenant')->tax_rate ?? 0);
            foreach (EduClass::whereIn('id', $d['class_ids'])->get() as $class) {
                foreach ($class->students()->where('status', 'active')->get() as $s) {
                    if (! $s->guardian_id) {
                        $skipped[] = $s->name;

                        continue;
                    }
                    foreach ($d['installments'] as $inst) {
                        $subject = $inst['label'].' — '.$s->name.($d['academic_year'] ?? '' ? ' ('.$d['academic_year'].')' : '');
                        if (Invoice::where('student_id', $s->id)->where('subject', $subject)->where('status', '!=', 'cancelled')->exists()) {
                            $dupes++;   // already billed: safe to re-run

                            continue;
                        }
                        $base = isset($inst['amount']) && $inst['amount'] !== null ? (float) $inst['amount'] : (float) $class->annual_fee * (float) ($inst['percent'] ?? 0) / 100;
                        if ($base <= 0) {
                            continue;
                        }
                        $disc = round($base * (float) $s->discount_pct / 100, 3);
                        $calc = DocumentCalculator::compute([['description' => $inst['label'].' — '.$class->name, 'qty' => 1, 'unit_price' => $base]], 'amount', $disc, $tax);
                        $inv = Invoice::create([
                            'number' => Sequence::next('sale', 'INV'), 'status' => $send ? 'sent' : 'draft', 'sent_at' => $send ? now() : null,
                            'customer_id' => $s->guardian_id, 'student_id' => $s->id, 'date' => now()->toDateString(), 'due_date' => $inst['due_date'],
                            'subject' => $subject, 'reference' => $s->student_no, 'discount_type' => 'amount', 'discount_value' => $disc, 'tax_rate' => $tax,
                            'subtotal' => $calc['subtotal'], 'discount' => $calc['discount'], 'tax' => $calc['tax'], 'total' => $calc['total'], 'created_by' => $request->user()->id,
                        ]);
                        foreach ($calc['lines'] as $l) {
                            InvoiceItem::create(['invoice_id' => $inv->id] + $l);
                        }
                        if ($send) {
                            $ledger->postInvoice($inv->load('customer'));
                        }
                        $made++;
                    }
                }
            }
        });

        return response()->json(['created' => $made, 'skipped_no_guardian' => $skipped, 'already_billed' => $dupes], 201);
    }
}
