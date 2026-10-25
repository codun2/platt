<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\RentalContract;
use App\Models\RentalPayment;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Sequence;
use App\Models\ServiceOrder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Double-entry ledger for the current tenant.
 *
 *  - ensureChart(): seeds the default Arabic chart of accounts once per tenant.
 *  - post(): writes a balanced journal entry (debits == credits).
 *  - reverse(): mirrors every entry of a source (used on cancel / delete).
 *  - postInvoice / postSale / postBooking (+ their payments): automatic postings from other engines.
 *
 * All auto-postings are idempotent per (source_type, source_id).
 */
class LedgerService
{
    /** Default chart: [code, name, type, system_key, parent code]. */
    public const CHART = [
        ['1000', 'الأصول', 'asset', null, null],
        ['1100', 'النقدية بالصندوق', 'asset', 'cash', '1000'],
        ['1200', 'البنك', 'asset', 'bank', '1000'],
        ['1300', 'ذمم العملاء (مدينون)', 'asset', 'ar', '1000'],
        ['1400', 'المخزون', 'asset', 'inventory', '1000'],
        ['1500', 'الأصول الثابتة', 'asset', 'fixed_assets', '1000'],
        ['1600', 'ضريبة القيمة المضافة (مدخلات)', 'asset', 'vat_in', '1000'],
        ['2000', 'الالتزامات', 'liability', null, null],
        ['2100', 'ذمم الموردين (دائنون)', 'liability', 'ap', '2000'],
        ['2200', 'ضريبة القيمة المضافة المستحقة', 'liability', 'vat_out', '2000'],
        ['2300', 'رواتب مستحقة', 'liability', 'salaries_payable', '2000'],
        ['2400', 'تأمينات العملاء', 'liability', 'customer_deposits', '2000'],
        ['2500', 'أرصدة محافظ العملاء', 'liability', 'customer_wallet', '2000'],
        ['2600', 'عرابين ودفعات مقدمة على الطلبات', 'liability', 'customer_advances', '2000'],
        ['3000', 'حقوق الملكية', 'equity', null, null],
        ['3100', 'رأس المال', 'equity', 'capital', '3000'],
        ['3200', 'الأرباح المحتجزة', 'equity', 'retained', '3000'],
        ['3300', 'مسحوبات المالك', 'equity', 'drawings', '3000'],
        ['4000', 'الإيرادات', 'revenue', null, null],
        ['4100', 'إيرادات المبيعات', 'revenue', 'sales', '4000'],
        ['4200', 'إيرادات الخدمات والحجوزات', 'revenue', 'service_revenue', '4000'],
        ['4300', 'خصومات مسموح بها', 'revenue', 'discounts', '4000'],
        ['4400', 'إيرادات الإيجارات', 'revenue', 'rent_revenue', '4000'],
        ['4900', 'إيرادات أخرى', 'revenue', 'other_income', '4000'],
        ['5000', 'المصروفات', 'expense', null, null],
        ['5100', 'تكلفة البضاعة المباعة', 'expense', 'cogs', '5000'],
        ['5200', 'الرواتب والأجور', 'expense', 'salaries', '5000'],
        ['5300', 'الإيجار', 'expense', 'rent', '5000'],
        ['5400', 'الكهرباء والماء والاتصالات', 'expense', 'utilities', '5000'],
        ['5500', 'التسويق والإعلان', 'expense', 'marketing', '5000'],
        ['5600', 'الصيانة والإصلاح', 'expense', 'maintenance', '5000'],
        ['5700', 'مصروفات إدارية وعمومية', 'expense', 'admin', '5000'],
        ['5800', 'عمولات ورسوم بنكية', 'expense', 'bank_fees', '5000'],
        ['5900', 'مصروفات أخرى', 'expense', 'other_exp', '5000'],
    ];

    /** Seed the default chart for the current tenant if it has no accounts yet. Idempotent. */
    public function ensureChart(): void
    {
        if (Account::exists()) {
            return;
        }
        $ids = [];
        foreach (self::CHART as [$code, $name, $type, $key, $parent]) {
            $a = Account::create([
                'code' => $code, 'name' => $name, 'type' => $type, 'system_key' => $key,
                'parent_id' => $parent ? ($ids[$parent] ?? null) : null, 'is_group' => $key === null,
            ]);
            $ids[$code] = $a->id;
        }
    }

    public function account(string $systemKey): Account
    {
        $this->ensureChart();

        $acc = Account::where('system_key', $systemKey)->first();
        if ($acc) {
            return $acc;
        }
        // A system account added to CHART after this tenant's chart was seeded
        // (e.g. rent_revenue): create it under its parent group on first use.
        foreach (self::CHART as [$code, $name, $type, $key, $parent]) {
            if ($key === $systemKey) {
                return Account::create([
                    'code' => $code, 'name' => $name, 'type' => $type, 'system_key' => $key, 'is_group' => false,
                    'parent_id' => $parent ? Account::where('code', $parent)->value('id') : null,
                ]);
            }
        }

        return Account::where('system_key', $systemKey)->firstOrFail();
    }

    /** cash | card | transfer | cheque | credit → account system key. */
    public function accountForMethod(string $method): Account
    {
        return $this->account(match ($method) {
            'cash' => 'cash', 'credit' => 'ar',
            'wallet' => 'customer_wallet',      // paid from the customer's prepaid balance
            'advance' => 'customer_advances',   // paid from a deposit taken on an order
            default => 'bank',
        });
    }

    /**
     * Post a balanced entry. $lines: [['account' => Account|id|system_key, 'debit' => x, 'credit' => y, 'description' => ?], ...]
     */
    public function post(array $lines, array $meta): JournalEntry
    {
        $this->ensureChart();
        $rows = [];
        $dr = 0.0;
        $cr = 0.0;
        foreach ($lines as $i => $l) {
            $d = round((float) ($l['debit'] ?? 0), 3);
            $c = round((float) ($l['credit'] ?? 0), 3);
            if ($d <= 0 && $c <= 0) {
                continue;
            }
            $acc = $l['account'];
            $accountId = $acc instanceof Account ? $acc->id : (is_numeric($acc) ? (int) $acc : $this->account((string) $acc)->id);
            $rows[] = ['account_id' => $accountId, 'debit' => $d, 'credit' => $c, 'description' => $l['description'] ?? null, 'sort' => $i];
            $dr += $d;
            $cr += $c;
        }
        if (count($rows) < 2) {
            throw new InvalidArgumentException('القيد يحتاج إلى طرفين على الأقل.');
        }
        if (abs($dr - $cr) > 0.0005) {
            throw new InvalidArgumentException('القيد غير متوازن: مدين '.number_format($dr, 3).' ≠ دائن '.number_format($cr, 3));
        }

        return DB::transaction(function () use ($rows, $meta, $dr) {
            $e = JournalEntry::create([
                'number' => Sequence::next('journal', 'JE'),
                'date' => $meta['date'] ?? now()->toDateString(),
                'reference' => $meta['reference'] ?? null,
                'description' => $meta['description'] ?? null,
                'status' => $meta['status'] ?? 'posted',
                'source_type' => $meta['source_type'] ?? 'manual',
                'source_id' => $meta['source_id'] ?? null,
                'total' => round($dr, 3),
                'created_by' => $meta['user_id'] ?? auth()->id(),
                'posted_at' => ($meta['status'] ?? 'posted') === 'posted' ? now() : null,
            ]);
            foreach ($rows as $r) {
                JournalLine::create($r + ['journal_entry_id' => $e->id]);
            }

            return $e;
        });
    }

    /** Entries of a source that have not been reversed yet (a reversal points at the entry it mirrors). */
    private function liveEntries(string $sourceType, int $sourceId)
    {
        return JournalEntry::with('lines')->where('source_type', $sourceType)->where('source_id', $sourceId)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('journal_entries as r')->whereColumn('r.source_id', 'journal_entries.id')->where('r.source_type', 'reversal'))
            ->get();
    }

    /** Has a posting for this source already been made (and not reversed)? */
    public function posted(string $sourceType, int $sourceId): bool
    {
        return $this->liveEntries($sourceType, $sourceId)->isNotEmpty();
    }

    /** Mirror every live entry of a source with a reversing entry. No-op when nothing is posted. */
    public function reverse(string $sourceType, int $sourceId, ?string $description = null, ?string $date = null): void
    {
        $entries = $this->liveEntries($sourceType, $sourceId);
        foreach ($entries as $e) {
            $lines = $e->lines->map(fn ($l) => ['account' => $l->account_id, 'debit' => (float) $l->credit, 'credit' => (float) $l->debit, 'description' => $l->description])->all();
            $this->post($lines, ['date' => $date ?? now()->toDateString(), 'description' => $description ?? 'عكس قيد '.$e->number, 'reference' => "$sourceType:$sourceId", 'source_type' => 'reversal', 'source_id' => $e->id]);
        }
    }

    /* ----------------------------- CRM invoices ----------------------------- */

    /** Issue: Dr AR / Cr sales (net) + Cr VAT. */
    public function postInvoice(Invoice $inv): void
    {
        if ($this->posted('invoice', $inv->id) || (float) $inv->total <= 0) {
            return;
        }
        $net = round((float) $inv->subtotal - (float) $inv->discount, 3);
        $this->post([
            ['account' => 'ar', 'debit' => (float) $inv->total, 'description' => $inv->customer?->name],
            ['account' => 'sales', 'credit' => $net],
            ['account' => 'vat_out', 'credit' => (float) $inv->tax],
        ], ['date' => $inv->date->toDateString(), 'description' => 'فاتورة مبيعات '.$inv->number, 'reference' => $inv->number, 'source_type' => 'invoice', 'source_id' => $inv->id]);
    }

    /** Receipt: Dr cash/bank / Cr AR. */
    public function postInvoicePayment(InvoicePayment $p, Invoice $inv): void
    {
        if ($this->posted('invoice_payment', $p->id)) {
            return;
        }
        $this->post([
            ['account' => $this->accountForMethod($p->method), 'debit' => (float) $p->amount],
            ['account' => 'ar', 'credit' => (float) $p->amount, 'description' => $inv->customer?->name],
        ], ['date' => $p->date->toDateString(), 'description' => 'تحصيل فاتورة '.$inv->number, 'reference' => $p->reference ?: $inv->number, 'source_type' => 'invoice_payment', 'source_id' => $p->id]);
    }

    /* ------------------------------- POS sales ------------------------------ */

    /** Completed POS sale: revenue + VAT, receipt (or AR when credit), COGS vs inventory. */
    public function postSale(Sale $sale): void
    {
        if ($this->posted('sale', $sale->id) || (float) $sale->total <= 0) {
            return;
        }
        $net = round((float) $sale->subtotal - (float) $sale->discount, 3);
        $paid = (float) $sale->paid;
        $due = round((float) $sale->total - $paid, 3);
        $lines = [
            ['account' => 'sales', 'credit' => $net],
            ['account' => 'vat_out', 'credit' => (float) $sale->tax],
        ];
        if ($paid > 0) {
            $lines[] = ['account' => $this->accountForMethod($sale->payment_method === 'credit' ? 'cash' : $sale->payment_method), 'debit' => $paid];
        }
        if ($due > 0) {
            $lines[] = ['account' => 'ar', 'debit' => $due, 'description' => $sale->customer?->name];
        }
        $cogs = round((float) $sale->items()->selectRaw('coalesce(sum(qty * unit_cost),0) as c')->value('c'), 3);
        if ($cogs > 0) {
            $lines[] = ['account' => 'cogs', 'debit' => $cogs];
            $lines[] = ['account' => 'inventory', 'credit' => $cogs];
        }
        $this->post($lines, ['date' => $sale->date->toDateString(), 'description' => 'فاتورة نقطة بيع '.$sale->number, 'reference' => $sale->number, 'source_type' => 'sale', 'source_id' => $sale->id]);
    }

    public function postSalePayment(SalePayment $p, Sale $sale): void
    {
        if ($this->posted('sale_payment', $p->id)) {
            return;
        }
        $this->post([
            ['account' => $this->accountForMethod($p->method), 'debit' => (float) $p->amount],
            ['account' => 'ar', 'credit' => (float) $p->amount, 'description' => $sale->customer?->name],
        ], ['date' => $p->date->toDateString(), 'description' => 'تحصيل فاتورة '.$sale->number, 'reference' => $sale->number, 'source_type' => 'sale_payment', 'source_id' => $p->id]);
    }

    /* -------------------------------- bookings ------------------------------- */

    /** Cash-basis booking money: payments → service revenue; deposits → customer deposits liability. */
    public function postBookingPayment(BookingPayment $p, Booking $b): void
    {
        if ($this->posted('booking_payment', $p->id)) {
            return;
        }
        $amt = (float) $p->amount;
        $money = $this->accountForMethod($p->method);
        $lines = match ($p->kind) {
            'payment' => [['account' => $money, 'debit' => $amt], ['account' => 'service_revenue', 'credit' => $amt]],
            'refund' => [['account' => 'service_revenue', 'debit' => $amt], ['account' => $money, 'credit' => $amt]],
            'deposit' => [['account' => $money, 'debit' => $amt], ['account' => 'customer_deposits', 'credit' => $amt]],
            'deposit_return' => [['account' => 'customer_deposits', 'debit' => $amt], ['account' => $money, 'credit' => $amt]],
            default => [],
        };
        if (! $lines) {
            return;
        }
        $this->post($lines, ['date' => $p->date->toDateString(), 'description' => 'حجز '.$b->number.' — '.$p->kind, 'reference' => $b->number, 'source_type' => 'booking_payment', 'source_id' => $p->id]);
    }

    /**
     * Rental payment: rent (lease) → rent revenue; installment sale → sales;
     * security deposit is a liability until returned.
     */
    public function postRentalPayment(RentalPayment $p, RentalContract $c): void
    {
        if ($this->posted('rental_payment', $p->id)) {
            return;
        }
        $amt = (float) $p->amount;
        $money = $this->accountForMethod($p->method);
        $income = $c->kind === 'installment' ? 'sales' : 'rent_revenue';
        $lines = match ($p->kind) {
            'rent' => [['account' => $money, 'debit' => $amt], ['account' => $income, 'credit' => $amt]],
            'deposit' => [['account' => $money, 'debit' => $amt], ['account' => 'customer_deposits', 'credit' => $amt]],
            'deposit_return' => [['account' => 'customer_deposits', 'debit' => $amt], ['account' => $money, 'credit' => $amt]],
            default => [],
        };
        if (! $lines) {
            return;
        }
        $label = ['rent' => 'دفعة', 'deposit' => 'تأمين', 'deposit_return' => 'إرجاع تأمين'][$p->kind];
        $this->post($lines, ['date' => $p->date->toDateString(), 'description' => 'عقد '.$c->number.' — '.$label, 'reference' => $c->number, 'source_type' => 'rental_payment', 'source_id' => $p->id]);
    }

    /** Cost of parts / materials used on a delivered work order: Dr COGS / Cr inventory. */
    public function postServiceCost(ServiceOrder $o, float $cost): void
    {
        if ($cost <= 0 || $this->posted('service_order', $o->id)) {
            return;
        }
        $this->post([['account' => 'cogs', 'debit' => $cost], ['account' => 'inventory', 'credit' => $cost]],
            ['date' => now()->toDateString(), 'description' => 'تكلفة قطع ومواد أمر العمل '.$o->number, 'reference' => $o->number, 'source_type' => 'service_order', 'source_id' => $o->id]);
    }

    /* -------------------------------- balances ------------------------------- */

    /**
     * Net movement per account between dates (inclusive), signed by the account's natural side
     * (debit-normal accounts: debit − credit; credit-normal: credit − debit).
     */
    public function balances(?string $from, ?string $to, bool $includeOpening = true): array
    {
        $this->ensureChart();
        $q = JournalLine::query()
            ->join('journal_entries as je', 'je.id', '=', 'journal_lines.journal_entry_id')
            ->where('je.tenant_id', app('currentTenantId'))->where('je.status', 'posted');
        if ($from) {
            $q->whereDate('je.date', '>=', $from);
        }
        if ($to) {
            $q->whereDate('je.date', '<=', $to);
        }
        $sums = $q->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id, sum(debit) as dr, sum(credit) as cr')
            ->get()->keyBy('account_id');

        $out = [];
        foreach (Account::orderBy('code')->get() as $a) {
            $dr = (float) ($sums[$a->id]->dr ?? 0);
            $cr = (float) ($sums[$a->id]->cr ?? 0);
            $opening = $includeOpening && ! $from ? (float) $a->opening_balance : ($includeOpening && $from ? (float) $a->opening_balance : 0);
            $out[$a->id] = ['account' => $a, 'debit' => $dr, 'credit' => $cr, 'opening' => $opening,
                'balance' => round(($a->isDebitNormal() ? $dr - $cr : $cr - $dr) + $opening, 3)];
        }

        return $out;
    }
}
