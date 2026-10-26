<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SerialUnit extends Model
{
    use BelongsToTenant;

    public const OUT = ['sold', 'installed'];

    protected $fillable = [
        'tenant_id', 'item_id', 'serial_no', 'status', 'warehouse_id', 'customer_id', 'customer_name',
        'warranty_months', 'warranty_start', 'warranty_until', 'purchase_id', 'out_ref', 'replaced_by_id',
        'delivered_at', 'location', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return ['warranty_start' => 'date:Y-m-d', 'warranty_until' => 'date:Y-m-d', 'delivered_at' => 'date:Y-m-d', 'warranty_months' => 'integer'];
    }

    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function events(): HasMany { return $this->hasMany(SerialEvent::class)->orderByDesc('id'); }

    public function isOut(): bool { return in_array($this->status, self::OUT, true); }
}
