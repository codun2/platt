<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'type', 'applies_to', 'category_ids', 'item_ids', 'value', 'buy_qty', 'get_qty', 'min_total', 'max_discount',
        'code', 'starts_on', 'ends_on', 'days', 'time_from', 'time_to', 'usage_limit', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'category_ids' => 'array', 'item_ids' => 'array', 'days' => 'array', 'is_active' => 'boolean',
            'value' => 'decimal:3', 'min_total' => 'decimal:3', 'max_discount' => 'decimal:3', 'discount_given' => 'decimal:3',
            'starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d', 'buy_qty' => 'integer', 'get_qty' => 'integer',
            'usage_limit' => 'integer', 'used_count' => 'integer',
        ];
    }

    public function isBill(): bool { return in_array($this->type, ['bill_percent', 'bill_amount'], true); }

    /** Valid right now (dates, weekday, hours, usage)? */
    public function liveAt(\DateTimeInterface $at): bool
    {
        $d = \Carbon\Carbon::instance($at);
        if (! $this->is_active || ($this->usage_limit && $this->used_count >= $this->usage_limit)) {
            return false;
        }
        if (($this->starts_on && $d->toDateString() < $this->starts_on->toDateString()) || ($this->ends_on && $d->toDateString() > $this->ends_on->toDateString())) {
            return false;
        }
        if ($this->days && ! in_array($d->dayOfWeek, array_map('intval', $this->days), true)) {
            return false;
        }
        $time = $d->format('H:i:s');
        if ($this->time_from && $this->time_to) {
            // An overnight window (e.g. 22:00 → 02:00) wraps past midnight.
            return $this->time_from <= $this->time_to ? ($time >= $this->time_from && $time <= $this->time_to) : ($time >= $this->time_from || $time <= $this->time_to);
        }

        return true;
    }
}
