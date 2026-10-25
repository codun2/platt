<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'number', 'client_ref', 'offline', 'sold_at', 'stock_short', 'customer_id', 'warehouse_id', 'shift_id', 'date', 'status',
        'subtotal', 'discount', 'tax_rate', 'tax', 'total', 'paid', 'payment_method', 'note', 'created_by', 'cancelled_at',
        'points_redeemed', 'points_discount', 'points_earned',
    ];

    protected function casts(): array
    {
        return [
            'offline' => 'boolean', 'stock_short' => 'boolean', 'sold_at' => 'datetime:Y-m-d H:i',
            'date' => 'date:Y-m-d', 'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:3', 'discount' => 'decimal:3', 'tax_rate' => 'decimal:2', 'tax' => 'decimal:3',
            'total' => 'decimal:3', 'paid' => 'decimal:3',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
