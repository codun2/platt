<?php

namespace App\Services;

use App\Models\BatchMove;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\Tenant;

/**
 * Add-on «الدفعات وتواريخ الصلاحية». Called by InventoryService::move() for items
 * with track_expiry, so every stock change keeps the batches in step:
 *
 *  - stock out: earliest expiry first (FEFO); a batch named in opts['batch_id'] or
 *    batches that came in by the same document (e.g. a cancelled purchase) go first;
 *  - stock in: a purchase line creates its batch (opts['batch']); a transfer copies the
 *    batches it took (opts['batches']); a cancel puts quantities back into the batches
 *    its document used; anything else becomes a batch with no date.
 * Stock that existed before tracking started simply has no batch («بدون دفعة»).
 */
class BatchService
{
    /** Batches the last stock-out used: [[batch, qty], …] (read by transfer()). */
    public array $lastTaken = [];

    public static function enabled(?Tenant $tenant = null): bool
    {
        $tenant ??= app()->bound('currentTenant') ? app('currentTenant') : null;

        return $tenant && in_array('batches', $tenant->enabledModuleKeys(), true);
    }

    public function onMove(Item $item, int $wh, float $qty, string $type, array $opts): void
    {
        $refType = $opts['ref_type'] ?? null;
        $refId = $opts['ref_id'] ?? null;
        $qty < 0 ? $this->take($item, $wh, -$qty, $type, $refType, $refId, $opts['batch_id'] ?? null) : $this->put($item, $wh, $qty, $type, $refType, $refId, $opts);
    }

    private function take(Item $item, int $wh, float $need, string $type, ?string $refType, $refId, $batchId): void
    {
        $this->lastTaken = [];
        $q = ItemBatch::where('item_id', $item->id)->where('warehouse_id', $wh)->where('qty_left', '>', 0)->lockForUpdate();
        $batches = $q->get()->sortBy(fn (ItemBatch $b) => [
            $batchId && $b->id == $batchId ? 0 : 1,                                   // the chosen batch
            $refType && $b->ref_type === $refType && $b->ref_id == $refId ? 0 : 1,    // same document (cancel)
            $b->expires_on ? $b->expires_on->format('Ymd') : '99999999',              // FEFO, undated last
            $b->id,
        ]);
        foreach ($batches as $b) {
            if ($need <= 0.0005) {
                break;
            }
            $use = min($need, (float) $b->qty_left);
            $b->update(['qty_left' => round((float) $b->qty_left - $use, 3)]);
            BatchMove::create(['batch_id' => $b->id, 'qty' => -$use, 'type' => $type, 'ref_type' => $refType, 'ref_id' => $refId]);
            $this->lastTaken[] = [$b, $use];
            $need -= $use;
        }
    }

    private function put(Item $item, int $wh, float $qty, string $type, ?string $refType, $refId, array $opts): void
    {
        // Purchase line: its own batch with the supplier's batch no / expiry.
        if (! empty($opts['batch'])) {
            $this->create($item, $wh, $qty, $opts['batch']['batch_no'] ?? null, $opts['batch']['expires_on'] ?? null, $opts['unit_cost'] ?? null, $refType, $refId, $type);

            return;
        }
        // Transfer in: the same batches that left the other warehouse.
        if (! empty($opts['batches'])) {
            foreach ($opts['batches'] as [$src, $use]) {
                $this->create($item, $wh, $use, $src->batch_no, $src->expires_on?->toDateString(), $src->unit_cost, $refType, $refId, $type);
                $qty -= $use;
            }
        }
        // A cancel / return: back into the batches this document took from.
        if ($qty > 0.0005 && $refType && $refId) {
            $taken = BatchMove::where('ref_type', $refType)->where('ref_id', $refId)->selectRaw('batch_id, -sum(qty) as net')->groupBy('batch_id')->having('net', '>', 0)->get();
            foreach ($taken as $t) {
                $b = ItemBatch::whereKey($t->batch_id)->where('warehouse_id', $wh)->lockForUpdate()->first();
                if (! $b || $qty <= 0.0005) {
                    continue;
                }
                $back = min($qty, (float) $t->net);
                $b->update(['qty_left' => round((float) $b->qty_left + $back, 3)]);
                BatchMove::create(['batch_id' => $b->id, 'qty' => $back, 'type' => $type, 'ref_type' => $refType, 'ref_id' => $refId]);
                $qty -= $back;
            }
        }
        if ($qty > 0.0005) {
            $this->create($item, $wh, $qty, null, null, $opts['unit_cost'] ?? null, $refType, $refId, $type);
        }
    }

    public function create(Item $item, int $wh, float $qty, ?string $no, ?string $expires, $cost, ?string $refType, $refId, string $type, ?string $note = null): ItemBatch
    {
        $b = ItemBatch::create([
            'item_id' => $item->id, 'warehouse_id' => $wh, 'batch_no' => $no ?: null, 'expires_on' => $expires ?: null,
            'qty_in' => $qty, 'qty_left' => $qty, 'unit_cost' => $cost, 'ref_type' => $refType, 'ref_id' => $refId, 'note' => $note,
        ]);
        BatchMove::create(['batch_id' => $b->id, 'qty' => $qty, 'type' => $type, 'ref_type' => $refType, 'ref_id' => $refId]);

        return $b;
    }
}
