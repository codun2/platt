<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'sku', 'barcode', 'name', 'name_en', 'category_id', 'unit_id',
        'cost_price', 'sale_price', 'track_stock', 'min_stock', 'is_active', 'image', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:3', 'sale_price' => 'decimal:3', 'min_stock' => 'decimal:3',
            'track_stock' => 'boolean', 'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
