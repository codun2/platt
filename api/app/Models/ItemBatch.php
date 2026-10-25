<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemBatch extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'item_id', 'warehouse_id', 'batch_no', 'expires_on', 'qty_in', 'qty_left', 'unit_cost', 'ref_type', 'ref_id', 'note'];

    protected function casts(): array
    {
        return ['expires_on' => 'date:Y-m-d', 'qty_in' => 'decimal:3', 'qty_left' => 'decimal:3', 'unit_cost' => 'decimal:3'];
    }

    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
}
