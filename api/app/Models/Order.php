<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToTenant;

    public const FLOW = ['new', 'confirmed', 'preparing', 'ready', 'out', 'delivered'];

    protected $fillable = [
        'tenant_id', 'number', 'customer_id', 'customer_name', 'customer_phone', 'channel', 'fulfilment', 'due_at',
        'address', 'driver', 'delivery_fee', 'status', 'subtotal', 'discount', 'tax_rate', 'tax', 'total', 'paid', 'notes',
        'warehouse_id', 'invoice_id', 'created_by', 'ready_at', 'delivered_at', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime', 'ready_at' => 'datetime', 'delivered_at' => 'datetime', 'cancelled_at' => 'datetime',
            'delivery_fee' => 'decimal:3', 'subtotal' => 'decimal:3', 'discount' => 'decimal:3', 'tax_rate' => 'decimal:3',
            'tax' => 'decimal:3', 'total' => 'decimal:3', 'paid' => 'decimal:3',
        ];
    }

    public function lines(): HasMany { return $this->hasMany(OrderLine::class)->orderBy('sort'); }
    public function payments(): HasMany { return $this->hasMany(OrderPayment::class)->orderBy('id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function isOpen(): bool { return ! in_array($this->status, ['delivered', 'cancelled'], true); }
}
