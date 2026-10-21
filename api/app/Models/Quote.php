<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'];

    protected $fillable = [
        'tenant_id', 'number', 'customer_id', 'date', 'valid_until', 'status', 'subject', 'reference',
        'subtotal', 'discount_type', 'discount_value', 'discount', 'tax_rate', 'tax', 'total',
        'terms', 'notes', 'internal_notes', 'converted_invoice_id', 'created_by', 'sent_at', 'accepted_at',
    ];

    protected $appends = ['is_expired'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d', 'valid_until' => 'date:Y-m-d',
            'sent_at' => 'datetime', 'accepted_at' => 'datetime',
            'subtotal' => 'decimal:3', 'discount_value' => 'decimal:3', 'discount' => 'decimal:3',
            'tax_rate' => 'decimal:2', 'tax' => 'decimal:3', 'total' => 'decimal:3',
        ];
    }

    /** Still open (draft/sent) but validity date has passed. */
    public function getIsExpiredAttribute(): bool
    {
        return in_array($this->status, ['draft', 'sent'], true)
            && $this->valid_until !== null
            && $this->valid_until->lt(now()->startOfDay());
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
