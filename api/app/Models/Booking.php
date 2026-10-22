<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'number', 'unit_id', 'customer_id', 'customer_name', 'customer_phone', 'customer_email', 'period', 'start_at', 'end_at', 'qty', 'unit_price', 'services_total', 'discount', 'tax_rate', 'tax', 'total', 'paid', 'deposit', 'deposit_paid', 'deposit_status', 'status', 'event_type', 'source', 'guests', 'adults', 'children', 'note', 'special_requests', 'internal_note', 'checked_in_at', 'checked_out_at', 'created_by'];

    protected function casts(): array
    {
        return ['start_at' => 'datetime', 'end_at' => 'datetime', 'checked_in_at' => 'datetime', 'checked_out_at' => 'datetime', 'qty' => 'decimal:2', 'unit_price' => 'decimal:3', 'services_total' => 'decimal:3', 'discount' => 'decimal:3', 'tax_rate' => 'decimal:2', 'tax' => 'decimal:3', 'total' => 'decimal:3', 'paid' => 'decimal:3', 'deposit' => 'decimal:3', 'deposit_paid' => 'decimal:3'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(BookingUnit::class, 'unit_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BookingLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
