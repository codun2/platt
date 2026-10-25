<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Promotion;
use App\Models\Tenant;

/**
 * Add-on «العروض والكوبونات» — works out the discounts for a basket.
 *
 *  - Line offers (percent, amount per unit, buy X get Y) on all items, chosen
 *    categories or chosen items: each line gets the single best one.
 *  - Bill offers (bill_percent, bill_amount) above a minimum subtotal: the best one.
 *  - A coupon (promotion with a code) only counts when its code is typed, and adds
 *    to the automatic offers (its line part still competes per line).
 * The checkout preview and the saved sale both use evaluate(), so they agree.
 */
class PromotionService
{
    public static function enabled(?Tenant $tenant = null): bool
    {
        $tenant ??= app()->bound('currentTenant') ? app('currentTenant') : null;

        return $tenant && in_array('promotions', $tenant->enabledModuleKeys(), true);
    }

    /**
     * @param  array  $lines  [['item_id' => id, 'qty' => n, 'unit_price' => p], ...]
     * @return array{lines: array<int,float>, bill: float, total: float, applied: array, coupon: ?array, coupon_error: ?string}
     */
    public function evaluate(array $lines, ?string $code = null, ?\DateTimeInterface $at = null): array
    {
        $at ??= now();
        $live = Promotion::where('is_active', true)->get()->filter(fn (Promotion $p) => $p->liveAt($at));
        $auto = $live->whereNull('code');
        $coupon = null;
        $couponError = null;
        if ($code = trim((string) $code)) {
            $coupon = Promotion::whereRaw('lower(code) = ?', [mb_strtolower($code)])->first();
            if (! $coupon) {
                $couponError = 'رمز الكوبون غير صحيح.';
            } elseif (! $coupon->liveAt($at)) {
                $couponError = $coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit ? 'تم استخدام هذا الكوبون بالحد الأقصى.' : 'هذا الكوبون غير متاح الآن (خارج فترة العرض).';
                $coupon = null;
            }
        }
        $candidates = $coupon ? $auto->push($coupon) : $auto;

        $categories = Item::whereIn('id', collect($lines)->pluck('item_id')->filter()->unique())->pluck('category_id', 'id');
        $subtotal = 0.0;
        foreach ($lines as $l) {
            $subtotal += (float) $l['qty'] * (float) $l['unit_price'];
        }

        $applied = [];   // promotion id → amount
        $lineDiscounts = [];
        foreach ($lines as $i => $l) {
            $best = 0.0;
            $bestId = null;
            foreach ($candidates as $p) {
                if ($p->isBill() || ! $this->covers($p, (int) $l['item_id'], $categories[$l['item_id']] ?? null) || $subtotal + 0.0005 < (float) $p->min_total) {
                    continue;
                }
                $amt = $this->lineAmount($p, (float) $l['qty'], (float) $l['unit_price']);
                if ($amt > $best + 0.0005) {
                    [$best, $bestId] = [$amt, $p->id];
                }
            }
            $lineDiscounts[$i] = round($best, 3);
            if ($bestId) {
                $applied[$bestId] = ($applied[$bestId] ?? 0) + round($best, 3);
            }
        }

        // Bill offers on what is left after the line offers.
        $afterLines = max(0, $subtotal - array_sum($lineDiscounts));
        $bill = 0.0;
        $billId = null;
        foreach ($candidates as $p) {
            if (! $p->isBill() || $subtotal + 0.0005 < (float) $p->min_total) {
                continue;
            }
            $amt = $p->type === 'bill_percent' ? $afterLines * (float) $p->value / 100 : (float) $p->value;
            if ($p->max_discount !== null && (float) $p->max_discount > 0) {
                $amt = min($amt, (float) $p->max_discount);
            }
            $amt = min($amt, $afterLines);
            if ($amt > $bill + 0.0005) {
                [$bill, $billId] = [$amt, $p->id];
            }
        }
        if ($billId) {
            $applied[$billId] = ($applied[$billId] ?? 0) + round($bill, 3);
        }

        $names = $candidates->keyBy('id');
        $list = collect($applied)->filter(fn ($a) => $a > 0)->map(fn ($a, $id) => ['id' => $id, 'name' => $names[$id]->name, 'amount' => round($a, 3), 'coupon' => (bool) $names[$id]->code])->values()->all();
        if ($coupon && ! isset($applied[$coupon->id])) {
            $couponError = (float) $coupon->min_total > $subtotal + 0.0005 ? 'هذا الكوبون يتطلب مشتريات بقيمة '.(float) $coupon->min_total.' على الأقل.' : 'لا ينطبق هذا الكوبون على أصناف السلة.';
        }

        return [
            'lines' => $lineDiscounts, 'bill' => round($bill, 3), 'total' => round(array_sum($lineDiscounts) + $bill, 3),
            'applied' => $list, 'coupon' => $coupon && isset($applied[$coupon->id]) ? ['code' => $coupon->code, 'name' => $coupon->name] : null,
            'coupon_error' => $couponError,
        ];
    }

    private function covers(Promotion $p, int $itemId, $categoryId): bool
    {
        return match ($p->applies_to) {
            'items' => in_array($itemId, array_map('intval', $p->item_ids ?? []), true),
            'categories' => $categoryId && in_array((int) $categoryId, array_map('intval', $p->category_ids ?? []), true),
            default => true,
        };
    }

    private function lineAmount(Promotion $p, float $qty, float $price): float
    {
        $gross = $qty * $price;
        $amt = match ($p->type) {
            'percent' => $gross * (float) $p->value / 100,
            'amount' => min($price, (float) $p->value) * $qty,
            // Buy N get M free: every full group of N+M units has M free units.
            'bxgy' => ($p->buy_qty > 0 && $p->get_qty > 0) ? floor($qty / ($p->buy_qty + $p->get_qty)) * $p->get_qty * $price : 0,
            default => 0,
        };
        if ($p->max_discount !== null && (float) $p->max_discount > 0) {
            $amt = min($amt, (float) $p->max_discount);
        }

        return min($amt, $gross);
    }

    /** After a sale: count uses and the discount each offer gave. */
    public function recordUse(array $applied): void
    {
        foreach ($applied as $a) {
            Promotion::whereKey($a['id'])->increment('used_count', 1, ['discount_given' => \Illuminate\Support\Facades\DB::raw('discount_given + '.(float) $a['amount'])]);
        }
    }
}
