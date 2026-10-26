<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Item;
use App\Models\SerialEvent;
use App\Models\SerialUnit;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

/**
 * Add-on «الأرقام التسلسلية والضمان». A registry overlaid on stock (does not move
 * quantities): registers each physical unit at receipt/opening, hands it to a
 * customer with a warranty, and keeps a timeline — for lookups and warranty claims.
 */
class SerialService
{
    private static function kinds(): array { return \App\Models\SerialUnit::OUT; }

    public static function enabled(?Tenant $tenant = null): bool
    {
        $tenant ??= app()->bound('currentTenant') ? app('currentTenant') : null;

        return $tenant && in_array('serials', $tenant->enabledModuleKeys(), true);
    }

    /** Warranty end from a start date + a number of months (null if unknown). */
    public function warrantyUntil(?string $start, ?int $months): ?string
    {
        return $start && $months ? \Carbon\Carbon::parse($start)->addMonthsNoOverflow($months)->toDateString() : null;
    }

    private function log(SerialUnit $u, string $type, ?string $from, ?string $to, ?string $ref = null, ?string $note = null): void
    {
        SerialEvent::create(['serial_unit_id' => $u->id, 'type' => $type, 'from_status' => $from, 'to_status' => $to, 'ref' => $ref, 'note' => $note, 'user_id' => auth()->id()]);
    }

    /**
     * Register in-stock units for an item (opening stock or just received). Does not
     * move stock. Rejects duplicates. $serials: list of serial strings.
     *
     * @return array{created:int, skipped:array}
     */
    public function register(Item $item, int $warehouseId, array $serials, array $opts = []): array
    {
        $months = $opts['warranty_months'] ?? $item->warranty_months;
        $clean = collect($serials)->map(fn ($s) => trim((string) $s))->filter()->unique()->values();
        if ($clean->isEmpty()) {
            throw ValidationException::withMessages(['serials' => 'أدخل رقماً تسلسلياً واحداً على الأقل.']);
        }
        $exists = SerialUnit::whereIn('serial_no', $clean)->pluck('serial_no')->flip();
        $created = 0;
        $skipped = [];
        foreach ($clean as $sn) {
            if (isset($exists[$sn])) {
                $skipped[] = $sn;

                continue;
            }
            $u = SerialUnit::create([
                'item_id' => $item->id, 'serial_no' => $sn, 'status' => 'in_stock', 'warehouse_id' => $warehouseId,
                'warranty_months' => $months, 'purchase_id' => $opts['purchase_id'] ?? null, 'note' => $opts['note'] ?? null,
                'created_by' => auth()->id(),
            ]);
            $this->log($u, $opts['purchase_id'] ?? false ? 'received' : 'opening', null, 'in_stock', $opts['ref'] ?? null);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** Hand a unit to a customer (sold / installed): sets owner, warranty start/end. */
    public function deliver(SerialUnit $u, string $as, array $d): SerialUnit
    {
        if (! in_array($u->status, ['in_stock', 'returned'], true)) {
            throw ValidationException::withMessages(['status' => "الوحدة {$u->serial_no} ليست متاحة في المخزن."]);
        }
        $start = $d['warranty_start'] ?? ($d['delivered_at'] ?? now()->toDateString());
        $months = $d['warranty_months'] ?? $u->warranty_months;
        $from = $u->status;
        $customer = ! empty($d['customer_id']) ? Customer::find($d['customer_id']) : null;
        $u->update([
            'status' => $as, 'warehouse_id' => null,
            'customer_id' => $customer?->id, 'customer_name' => $customer?->name ?: ($d['customer_name'] ?? null),
            'warranty_months' => $months, 'warranty_start' => $start, 'warranty_until' => $this->warrantyUntil($start, $months),
            'out_ref' => $d['out_ref'] ?? null, 'delivered_at' => $d['delivered_at'] ?? now()->toDateString(), 'location' => $d['location'] ?? null,
        ]);
        $this->log($u, 'delivered', $from, $as, $d['out_ref'] ?? null, $d['note'] ?? null);

        return $u;
    }

    /** Status change with a timeline note (return, faulty, scrapped). */
    public function setStatus(SerialUnit $u, string $status, ?string $note = null): SerialUnit
    {
        $from = $u->status;
        $u->update(['status' => $status, 'warehouse_id' => $status === 'returned' ? ($u->warehouse_id ?: null) : $u->warehouse_id]);
        $this->log($u, $status === 'returned' ? 'returned' : ($status === 'scrapped' ? 'scrapped' : 'faulty'), $from, $status, null, $note);

        return $u;
    }

    /** Replace a faulty unit under warranty with an in-stock unit for the same customer. */
    public function replace(SerialUnit $faulty, SerialUnit $with, ?string $note = null): SerialUnit
    {
        if ($with->status !== 'in_stock') {
            throw ValidationException::withMessages(['replacement' => 'بديل غير متاح في المخزن.']);
        }
        // Replacement keeps the original delivery kind (installed/sold), even if the
        // faulty unit was already marked faulty (read it back from its history).
        $kind = in_array($faulty->status, self::kinds(), true) ? $faulty->status
            : (SerialEvent::where('serial_unit_id', $faulty->id)->where('type', 'delivered')->orderByDesc('id')->value('to_status') ?: 'sold');
        $with->update([
            'status' => $kind, 'warehouse_id' => null,
            'customer_id' => $faulty->customer_id, 'customer_name' => $faulty->customer_name,
            'warranty_months' => $faulty->warranty_months, 'warranty_start' => now()->toDateString(),
            'warranty_until' => $this->warrantyUntil(now()->toDateString(), $faulty->warranty_months),
            'out_ref' => $faulty->out_ref, 'delivered_at' => now()->toDateString(), 'location' => $faulty->location,
        ]);
        $this->log($with, 'delivered', 'in_stock', $with->status, $faulty->serial_no, 'بديل عن '.$faulty->serial_no);
        $prev = $faulty->status;
        $faulty->update(['status' => 'faulty', 'replaced_by_id' => $with->id]);
        $this->log($faulty, 'replaced', $prev, 'faulty', $with->serial_no, $note ?: 'استُبدلت بـ '.$with->serial_no);

        return $with;
    }
}
