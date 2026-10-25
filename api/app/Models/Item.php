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
        'tenant_id', 'sku', 'barcode', 'name', 'name_en', 'brand', 'model', 'description', 'category_id', 'unit_id', 'supplier_id',
        'cost_price', 'sale_price', 'track_stock', 'track_expiry', 'min_stock', 'reorder_qty', 'max_stock', 'location', 'weight', 'is_active', 'show_online', 'image', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:3', 'sale_price' => 'decimal:3', 'min_stock' => 'decimal:3',
            'reorder_qty' => 'decimal:3', 'max_stock' => 'decimal:3', 'weight' => 'decimal:3',
            'track_stock' => 'boolean', 'track_expiry' => 'boolean', 'is_active' => 'boolean', 'show_online' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
