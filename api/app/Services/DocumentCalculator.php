<?php

namespace App\Services;

use App\Models\Item;

/**
 * Shared line + totals math for invoices and quotes so both documents (and
 * quote → invoice conversion) always agree to the millième.
 *
 * Returns ['lines' => [...], 'subtotal', 'discount', 'tax', 'total'].
 */
class DocumentCalculator
{
    public static function compute(array $lines, string $discountType, float $discountValue, float $taxRate): array
    {
        $subtotal = 0.0;
        $out = [];
        foreach (array_values($lines) as $i => $l) {
            $qty = (float) ($l['qty'] ?? 1);
            $price = (float) ($l['unit_price'] ?? 0);
            $disc = (float) ($l['discount'] ?? 0);
            $total = round(max(0, $qty * $price - $disc), 3);
            $subtotal += $total;

            $unit = $l['unit'] ?? null;
            $description = trim((string) ($l['description'] ?? ''));
            if (! empty($l['item_id'])) {
                $item = Item::with('unit:id,symbol,name')->find($l['item_id']);
                if ($item) {
                    $description = $description !== '' ? $description : $item->name;
                    $unit = $unit ?: ($item->unit?->symbol ?: $item->unit?->name);
                }
            }

            $out[] = [
                'item_id' => $l['item_id'] ?? null,
                'description' => $description,
                'qty' => $qty,
                'unit' => $unit ?: null,
                'unit_price' => $price,
                'discount' => $disc,
                'total' => $total,
                'sort' => $i,
            ];
        }

        $subtotal = round($subtotal, 3);
        $discount = $discountType === 'percent'
            ? round($subtotal * min(100, max(0, $discountValue)) / 100, 3)
            : round(min($subtotal, max(0, $discountValue)), 3);
        $taxable = max(0, $subtotal - $discount);
        $tax = round($taxable * $taxRate / 100, 3);
        $total = round($taxable + $tax, 3);

        return compact('subtotal', 'discount', 'tax', 'total') + ['lines' => $out];
    }
}
