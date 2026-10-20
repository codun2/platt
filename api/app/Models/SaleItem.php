<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['sale_id', 'item_id', 'name', 'qty', 'unit_price', 'unit_cost', 'discount', 'total'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'unit_price' => 'decimal:3', 'unit_cost' => 'decimal:3', 'discount' => 'decimal:3', 'total' => 'decimal:3'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
