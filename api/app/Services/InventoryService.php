<?php

namespace App\Services;

use App\Models\Item;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single entry point for every stock change: keeps stock_levels and
 * stock_movements consistent. Call inside a DB transaction.
 */
class InventoryService
{
    /**
     * Apply a signed quantity to an item in a warehouse.
     * Negative moves fail when stock would go below zero (unless $allowNegative).
     */
    public function move(Item $item, int $warehouseId, float $qty, string $type, array $opts = []): ?StockMovement
    {
        if (! $item->track_stock || $qty == 0) {
            return null;
        }

        $level = StockLevel::firstOrCreate(['item_id' => $item->id, 'warehouse_id' => $warehouseId], ['qty' => 0]);
        $level = StockLevel::whereKey($level->id)->lockForUpdate()->first();
        $new = (float) $level->qty + $qty;

        if ($new < 0 && empty($opts['allow_negative'])) {
            throw ValidationException::withMessages([
                'qty' => "الكمية المتاحة من «{$item->name}» غير كافية (المتاح: ".rtrim(rtrim(number_format($level->qty, 3, '.', ''), '0'), '.').').',
            ]);
        }

        $level->update(['qty' => $new]);

        // Add-on: items tracked by batch / expiry keep their batches in step.
        if ($item->track_expiry && BatchService::enabled()) {
            app(BatchService::class)->onMove($item, $warehouseId, $qty, $type, $opts);
        }

        return StockMovement::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouseId,
            'qty' => $qty,
            'balance_after' => $new,
            'type' => $type,
            'ref_type' => $opts['ref_type'] ?? null,
            'ref_id' => $opts['ref_id'] ?? null,
            'unit_cost' => $opts['unit_cost'] ?? null,
            'note' => $opts['note'] ?? null,
            'user_id' => auth()->id(),
        ]);
    }

    /** Set absolute quantity (stock count) — records the delta as an adjustment. */
    public function adjustTo(Item $item, int $warehouseId, float $targetQty, ?string $note = null): ?StockMovement
    {
        $current = (float) (StockLevel::where('item_id', $item->id)->where('warehouse_id', $warehouseId)->value('qty') ?? 0);
        $delta = $targetQty - $current;

        return $this->move($item, $warehouseId, $delta, 'adjustment', ['note' => $note, 'allow_negative' => true]);
    }

    public function transfer(Item $item, int $fromWarehouse, int $toWarehouse, float $qty, ?string $note = null): void
    {
        if ($fromWarehouse === $toWarehouse) {
            throw ValidationException::withMessages(['to_warehouse_id' => 'المستودعان متطابقان.']);
        }
        DB::transaction(function () use ($item, $fromWarehouse, $toWarehouse, $qty, $note) {
            $this->move($item, $fromWarehouse, -$qty, 'transfer_out', ['note' => $note]);
            // Batches travel with the goods (same batch no / expiry in the other warehouse).
            $batches = $item->track_expiry && BatchService::enabled() ? app(BatchService::class)->lastTaken : [];
            $this->move($item, $toWarehouse, $qty, 'transfer_in', ['note' => $note, 'batches' => $batches]);
        });
    }

    /** Stock of an item across warehouses (or in one). */
    public function qty(Item $item, ?int $warehouseId = null): float
    {
        $q = StockLevel::where('item_id', $item->id);
        if ($warehouseId) {
            $q->where('warehouse_id', $warehouseId);
        }

        return (float) $q->sum('qty');
    }

    public function defaultWarehouseId(): int
    {
        return Warehouse::defaultFor()->id;
    }
}
