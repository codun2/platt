<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderLine extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'order_id', 'item_id', 'qty_required', 'qty_consumed', 'unit_cost'];

    protected function casts(): array { return ['qty_required' => 'decimal:3', 'qty_consumed' => 'decimal:3', 'unit_cost' => 'decimal:3']; }

    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
}
