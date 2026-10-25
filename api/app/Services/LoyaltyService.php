<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

/**
 * Add-on «الولاء والمحفظة».
 *
 *  - Points: earned on every paid purchase (earn_rate points per 1 currency unit),
 *    redeemed at the checkout as a discount (point_value currency per point).
 *  - Wallet: prepaid balance. A top-up is cash in (Dr cash/bank, Cr customer_wallet);
 *    spending it is the sale's payment method «wallet» (Dr customer_wallet in postSale).
 *
 * Every change is one loyalty_transactions row; customers.points / wallet_balance
 * are the running totals (locked row while changing, never negative).
 */
class LoyaltyService
{
    public const DEFAULTS = [
        'earn_rate' => 1,         // points per 1 currency unit paid
        'point_value' => 0.01,    // currency value of 1 point when redeemed
        'min_redeem' => 100,      // smallest number of points that can be redeemed
        'max_redeem_percent' => 50, // points can pay at most this % of a bill
        'wallet_enabled' => true,
    ];

    public static function enabled(?Tenant $tenant = null): bool
    {
        $tenant ??= app()->bound('currentTenant') ? app('currentTenant') : null;

        return $tenant && in_array('loyalty', $tenant->enabledModuleKeys(), true);
    }

    public static function settings(?Tenant $tenant = null): array
    {
        $tenant ??= app('currentTenant');
        $own = (array) (($tenant->settings ?? [])['loyalty'] ?? []);

        return array_merge(self::DEFAULTS, array_intersect_key($own, self::DEFAULTS));
    }

    /** Points for a paid amount (whole points, rounded down). */
    public function pointsFor(float $amount): float
    {
        return floor(max(0, $amount) * (float) self::settings()['earn_rate']);
    }

    /** Currency value of a number of points. */
    public function valueOf(float $points): float
    {
        return round($points * (float) self::settings()['point_value'], 3);
    }

    /**
     * Check a redemption request at the checkout and return its discount value.
     * $billTotal is the amount before the points discount.
     */
    public function redemptionValue(Customer $c, float $points, float $billTotal): float
    {
        if ($points <= 0) {
            return 0;
        }
        $s = self::settings();
        if ($points < (float) $s['min_redeem']) {
            throw ValidationException::withMessages(['redeem_points' => "أقل عدد نقاط يمكن استبداله هو {$s['min_redeem']} نقطة."]);
        }
        if ($points > (float) $c->points + 0.0005) {
            throw ValidationException::withMessages(['redeem_points' => 'رصيد نقاط العميل لا يكفي (المتاح '.(float) $c->points.' نقطة).']);
        }
        $value = $this->valueOf($points);
        $cap = round($billTotal * (float) $s['max_redeem_percent'] / 100, 3);
        if ($value > $cap + 0.0005) {
            throw ValidationException::withMessages(['redeem_points' => "النقاط تغطي حتى {$s['max_redeem_percent']}% من الفاتورة فقط (حد أقصى ".$cap.').']);
        }

        return $value;
    }

    /**
     * Apply one change to a customer's points and/or wallet and log it.
     * $points / $amount are signed (+ adds, − takes).
     */
    public function record(Customer $c, string $type, float $points, float $amount, array $meta = []): LoyaltyTransaction
    {
        $c = Customer::whereKey($c->id)->lockForUpdate()->firstOrFail();
        $newPoints = round((float) $c->points + $points, 3);
        $newWallet = round((float) $c->wallet_balance + $amount, 3);
        if ($newPoints < -0.0005) {
            throw ValidationException::withMessages(['points' => 'رصيد نقاط العميل لا يكفي.']);
        }
        if ($newWallet < -0.0005) {
            throw ValidationException::withMessages(['amount' => 'رصيد محفظة العميل لا يكفي (المتاح '.(float) $c->wallet_balance.').']);
        }
        $c->forceFill(['points' => max(0, $newPoints), 'wallet_balance' => max(0, $newWallet)])->save();

        return LoyaltyTransaction::create([
            'customer_id' => $c->id, 'type' => $type, 'points' => $points, 'amount' => $amount,
            'points_after' => $c->points, 'wallet_after' => $c->wallet_balance,
            'method' => $meta['method'] ?? null, 'ref_type' => $meta['ref_type'] ?? null, 'ref_id' => $meta['ref_id'] ?? null,
            'reference' => $meta['reference'] ?? null, 'note' => $meta['note'] ?? null,
            'user_id' => auth()->id(),
        ]);
    }

    /** Wallet top-up received in cash / card / transfer (posts the money as a liability). */
    public function topUp(Customer $c, float $amount, string $method, ?string $note = null): LoyaltyTransaction
    {
        $t = $this->record($c, 'topup', 0, $amount, ['method' => $method, 'note' => $note]);
        $ledger = app(LedgerService::class);
        $ledger->post([
            ['account' => $ledger->accountForMethod($method), 'debit' => $amount],
            ['account' => 'customer_wallet', 'credit' => $amount, 'description' => $c->name],
        ], ['date' => now()->toDateString(), 'description' => 'شحن محفظة العميل '.$c->name, 'reference' => 'WAL-'.$t->id, 'source_type' => 'wallet_topup', 'source_id' => $t->id]);

        return $t;
    }

    /** Balance paid back to the customer (money out of the wallet). */
    public function refund(Customer $c, float $amount, string $method, ?string $note = null): LoyaltyTransaction
    {
        $t = $this->record($c, 'refund', 0, -$amount, ['method' => $method, 'note' => $note]);
        $ledger = app(LedgerService::class);
        $ledger->post([
            ['account' => 'customer_wallet', 'debit' => $amount, 'description' => $c->name],
            ['account' => $ledger->accountForMethod($method), 'credit' => $amount],
        ], ['date' => now()->toDateString(), 'description' => 'استرداد رصيد محفظة '.$c->name, 'reference' => 'WAL-'.$t->id, 'source_type' => 'wallet_refund', 'source_id' => $t->id]);

        return $t;
    }

    /** Undo everything a document did (cancelled sale): points and wallet go back. */
    public function reverseFor(string $refType, int $refId): void
    {
        foreach (LoyaltyTransaction::where('ref_type', $refType)->where('ref_id', $refId)->where('reversed', false)->get() as $t) {
            $c = Customer::find($t->customer_id);
            if (! $c) {
                continue;
            }
            // An earn that was already spent cannot take the balance below zero.
            $points = -(float) $t->points;
            if ($points < 0) {
                $points = -min(-$points, (float) $c->points);
            }
            $this->record($c, 'reversal', $points, -(float) $t->amount, ['ref_type' => $refType, 'ref_id' => $refId, 'reference' => $t->reference, 'note' => 'إلغاء: '.$t->reference]);
            $t->update(['reversed' => true]);
        }
    }
}
