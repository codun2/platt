<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceOrder extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'number', 'customer_id', 'customer_name', 'customer_phone', 'title', 'details', 'status', 'priority', 'technician_id',
        'received_at', 'promised_at', 'delivered_at', 'estimate', 'discount', 'subtotal', 'warehouse_id', 'invoice_id', 'diagnosis', 'notes',
        'internal_notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array', 'received_at' => 'datetime:Y-m-d H:i', 'promised_at' => 'datetime:Y-m-d H:i', 'delivered_at' => 'datetime:Y-m-d H:i',
            'estimate' => 'decimal:3', 'discount' => 'decimal:3', 'subtotal' => 'decimal:3',
        ];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function technician(): BelongsTo { return $this->belongsTo(ServiceTechnician::class, 'technician_id'); }
    public function lines(): HasMany { return $this->hasMany(ServiceOrderLine::class, 'order_id')->orderBy('sort'); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
