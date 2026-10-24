<?php

namespace App\Services;

use App\Models\RentalAsset;
use App\Models\RentalContract;
use App\Models\RentalInstallment;
use App\Models\RentalPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Rental engine rules:
 *  - a contract's installment schedule is generated from start/end/frequency
 *    (+ an optional down payment due on the start date for installment sales);
 *  - a payment is allocated to the chosen installment then the oldest unpaid
 *    ones, one RentalPayment per installment touched, each posted to the ledger;
 *  - ending / terminating a contract cancels untouched future installments and
 *    frees the asset.
 */
class RentalService
{
    public const FREQUENCIES = ['daily', 'weekly', 'monthly', 'quarterly', 'semiannual', 'annual', 'once'];

    /** Period start dates [start, nextStart) between start and end for a frequency. */
    public function periods(string $start, string $end, string $frequency): array
    {
        $from = Carbon::parse($start)->startOfDay();
        $to = Carbon::parse($end)->startOfDay();
        if ($to->lt($from)) {
            throw new InvalidArgumentException('تاريخ نهاية العقد يجب أن يكون بعد بدايته.');
        }
        if ($frequency === 'once') {
            return [[$from->copy(), $to->copy()]];
        }
        $out = [];
        $cur = $from->copy();
        $n = 0;
        while ($cur->lte($to)) {
            $next = $this->step($from, $frequency, ++$n);
            $out[] = [$cur->copy(), $next->copy()->subDay()->min($to)];
            $cur = $next;
            if (count($out) > 3660) {   // 10 years of daily periods: guard against runaway input
                throw new InvalidArgumentException('مدة العقد طويلة جداً لهذا التكرار.');
            }
        }

        return $out;
    }

    /** n-th period start counted from the contract start (avoids month-end drift). */
    private function step(Carbon $from, string $frequency, int $n): Carbon
    {
        return match ($frequency) {
            'daily' => $from->copy()->addDays($n),
            'weekly' => $from->copy()->addWeeks($n),
            'monthly' => $from->copy()->addMonthsNoOverflow($n),
            'quarterly' => $from->copy()->addMonthsNoOverflow(3 * $n),
            'semiannual' => $from->copy()->addMonthsNoOverflow(6 * $n),
            'annual' => $from->copy()->addYearsNoOverflow($n),
            default => throw new InvalidArgumentException('تكرار غير معروف.'),
        };
    }

    /** Preview without saving: [['seq','due_date','period_end','amount'], ...]. */
    public function schedule(array $c): array
    {
        $rows = [];
        $seq = 0;
        if ((float) ($c['down_payment'] ?? 0) > 0) {
            $rows[] = ['seq' => ++$seq, 'due_date' => Carbon::parse($c['start_date'])->toDateString(), 'period_end' => null, 'amount' => round((float) $c['down_payment'], 3), 'is_down' => true];
        }
        foreach ($this->periods($c['start_date'], $c['end_date'], $c['frequency']) as [$s, $e]) {
            $rows[] = ['seq' => ++$seq, 'due_date' => $s->toDateString(), 'period_end' => $e->toDateString(), 'amount' => round((float) $c['rent_amount'], 3), 'is_down' => false];
        }

        return $rows;
    }

    /** (Re)build the schedule of a contract that has no payments yet. */
    public function generate(RentalContract $c): void
    {
        if ($c->installments()->where('paid', '>', 0)->exists()) {
            throw new InvalidArgumentException('لا يمكن إعادة توليد الأقساط بعد تسجيل دفعات على العقد.');
        }
        $c->installments()->delete();
        $rows = $this->schedule([
            'start_date' => $c->start_date->toDateString(), 'end_date' => $c->end_date->toDateString(),
            'frequency' => $c->frequency, 'rent_amount' => $c->rent_amount, 'down_payment' => $c->down_payment,
        ]);
        foreach ($rows as $r) {
            RentalInstallment::create(['contract_id' => $c->id, 'seq' => $r['seq'], 'due_date' => $r['due_date'], 'period_end' => $r['period_end'], 'amount' => $r['amount']]);
        }
        $c->total = round(array_sum(array_column($rows, 'amount')), 3);
        $c->save();
    }

    /**
     * Collect an amount against a contract. Returns the created payments.
     * $installmentId (optional) is settled first, then the oldest unpaid.
     */
    public function collect(RentalContract $c, float $amount, string $method, string $date, ?int $installmentId, ?string $reference, ?string $note, ?int $userId): array
    {
        $amount = round($amount, 3);
        $open = $c->installments()->whereIn('status', ['due', 'partial'])->orderBy('due_date')->orderBy('seq')->get();
        if ($installmentId) {
            $open = $open->sortBy(fn ($i) => $i->id === $installmentId ? 0 : 1)->values();
        }
        $remaining = round($open->sum(fn ($i) => (float) $i->amount - (float) $i->paid), 3);
        if ($amount > $remaining + 0.0005) {
            throw new InvalidArgumentException('المبلغ أكبر من المتبقي على العقد ('.number_format($remaining, 3).').');
        }

        return DB::transaction(function () use ($c, $amount, $method, $date, $open, $reference, $note, $userId) {
            $left = $amount;
            $made = [];
            $ledger = app(LedgerService::class);
            foreach ($open as $inst) {
                if ($left <= 0.0005) {
                    break;
                }
                $take = round(min($left, (float) $inst->amount - (float) $inst->paid), 3);
                if ($take <= 0) {
                    continue;
                }
                $p = RentalPayment::create([
                    'contract_id' => $c->id, 'installment_id' => $inst->id, 'kind' => 'rent', 'amount' => $take,
                    'method' => $method, 'date' => $date, 'reference' => $reference, 'note' => $note, 'user_id' => $userId,
                ]);
                $ledger->postRentalPayment($p, $c);
                $inst->paid = round((float) $inst->paid + $take, 3);
                $this->refreshInstallment($inst, $date);
                $left = round($left - $take, 3);
                $made[] = $p;
            }
            $this->refreshContract($c);

            return $made;
        });
    }

    /** Security deposit received or returned. */
    public function deposit(RentalContract $c, string $kind, float $amount, string $method, string $date, ?string $note, ?int $userId): RentalPayment
    {
        $amount = round($amount, 3);
        if ($kind === 'deposit_return' && $amount > (float) $c->deposit_paid + 0.0005) {
            throw new InvalidArgumentException('المبلغ أكبر من التأمين المحصّل ('.number_format((float) $c->deposit_paid, 3).').');
        }

        return DB::transaction(function () use ($c, $kind, $amount, $method, $date, $note, $userId) {
            $p = RentalPayment::create(['contract_id' => $c->id, 'kind' => $kind, 'amount' => $amount, 'method' => $method, 'date' => $date, 'note' => $note, 'user_id' => $userId]);
            app(LedgerService::class)->postRentalPayment($p, $c);
            $c->deposit_paid = round((float) $c->deposit_paid + ($kind === 'deposit' ? $amount : -$amount), 3);
            $c->deposit_status = $c->deposit_paid > 0.0005 ? 'held' : ($kind === 'deposit_return' ? 'returned' : 'none');
            $c->save();

            return $p;
        });
    }

    /** End (natural expiry) or terminate early: cancel untouched future installments, free the asset. */
    public function close(RentalContract $c, string $status, string $date): void
    {
        DB::transaction(function () use ($c, $status, $date) {
            $c->installments()->where('paid', 0)->whereIn('status', ['due'])->where('due_date', '>', $date)->update(['status' => 'cancelled']);
            $c->status = $status;
            $c->terminated_at = $date;
            $this->refreshContract($c);
            $this->syncAssetStatus($c->asset);
        });
    }

    public function refreshInstallment(RentalInstallment $i, ?string $date = null): void
    {
        if ($i->status !== 'cancelled') {
            $i->status = (float) $i->paid >= (float) $i->amount - 0.0005 ? 'paid' : ((float) $i->paid > 0 ? 'partial' : 'due');
            $i->paid_at = $i->status === 'paid' ? ($i->paid_at ?? $date ?? now()->toDateString()) : null;
        }
        $i->save();
    }

    public function refreshContract(RentalContract $c): void
    {
        $c->paid = round((float) $c->installments()->sum('paid'), 3);
        $c->total = round((float) $c->installments()->where(fn ($q) => $q->where('status', '!=', 'cancelled')->orWhere('paid', '>', 0))->sum('amount'), 3);
        $c->save();
    }

    /** An asset is "rented" while it has an active contract. */
    public function syncAssetStatus(?RentalAsset $a): void
    {
        if (! $a || in_array($a->status, ['maintenance', 'inactive'], true)) {
            return;
        }
        $a->status = $a->contracts()->where('status', 'active')->exists() ? 'rented' : 'available';
        $a->save();
    }
}
