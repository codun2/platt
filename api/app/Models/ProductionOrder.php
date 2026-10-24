<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOrder extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'number', 'bom_id', 'item_id', 'planned_qty', 'actual_qty', 'status', 'planned_date', 'started_at', 'completed_at',
        'warehouse_id', 'material_cost', 'extra_cost', 'unit_cost', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'planned_qty' => 'decimal:3', 'actual_qty' => 'decimal:3', 'planned_date' => 'date:Y-m-d', 'started_at' => 'datetime:Y-m-d H:i', 'completed_at' => 'datetime:Y-m-d H:i',
            'material_cost' => 'decimal:3', 'extra_cost' => 'decimal:3', 'unit_cost' => 'decimal:3',
        ];
    }

    public function bom(): BelongsTo { return $this->belongsTo(Bom::class); }
    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function lines(): HasMany { return $this->hasMany(ProductionOrderLine::class, 'order_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
