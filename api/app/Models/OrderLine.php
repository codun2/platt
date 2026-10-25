<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'item_id', 'description', 'unit', 'qty', 'unit_price', 'discount', 'total', 'sort'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'unit_price' => 'decimal:3', 'discount' => 'decimal:3', 'total' => 'decimal:3'];
    }

    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
}
