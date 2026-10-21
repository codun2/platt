<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['draft', 'sent', 'partial', 'paid', 'cancelled'];

    protected $fillable = [
        'tenant_id', 'number', 'quote_id', 'customer_id', 'date', 'due_date', 'status', 'subject', 'reference',
        'subtotal', 'discount_type', 'discount_value', 'discount', 'tax_rate', 'tax', 'total', 'paid',
        'payment_terms_days', 'notes', 'internal_notes', 'created_by', 'sent_at', 'paid_at', 'cancelled_at',
    ];

    protected $appends = ['balance', 'is_overdue'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d', 'due_date' => 'date:Y-m-d',
            'sent_at' => 'datetime', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:3', 'discount_value' => 'decimal:3', 'discount' => 'decimal:3',
            'tax_rate' => 'decimal:2', 'tax' => 'decimal:3', 'total' => 'decimal:3', 'paid' => 'decimal:3',
            'payment_terms_days' => 'integer',
        ];
    }

    public function getBalanceAttribute(): float
    {
        return round((float) $this->total - (float) $this->paid, 3);
    }

    /** Unpaid, past due date, and not cancelled/draft. */
    public function getIsOverdueAttribute(): bool
    {
        return in_array($this->status, ['sent', 'partial'], true)
            && $this->due_date !== null
            && $this->due_date->lt(now()->startOfDay());
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderByDesc('date')->orderByDesc('id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
