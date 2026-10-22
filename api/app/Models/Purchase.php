<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'number', 'supplier_id', 'warehouse_id', 'date', 'due_date', 'supplier_invoice', 'reference', 'status',
        'subtotal', 'discount', 'tax', 'total', 'paid', 'payment_method', 'note', 'created_by', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d', 'due_date' => 'date:Y-m-d', 'received_at' => 'datetime',
            'subtotal' => 'decimal:3', 'discount' => 'decimal:3', 'tax' => 'decimal:3', 'total' => 'decimal:3', 'paid' => 'decimal:3',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
