<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderLine extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'order_id', 'kind', 'item_id', 'catalog_id', 'description', 'qty', 'unit_price', 'unit_cost', 'total', 'sort'];

    protected function casts(): array { return ['qty' => 'decimal:3', 'unit_price' => 'decimal:3', 'unit_cost' => 'decimal:3', 'total' => 'decimal:3']; }

    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
}
