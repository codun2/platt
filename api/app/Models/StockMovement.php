<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'item_id', 'warehouse_id', 'qty', 'balance_after', 'type', 'ref_type', 'ref_id', 'unit_cost', 'note', 'user_id'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'balance_after' => 'decimal:3', 'unit_cost' => 'decimal:3'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
