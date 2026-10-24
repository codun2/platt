<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalContract extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'number', 'kind', 'asset_id', 'customer_id', 'start_date', 'end_date', 'frequency', 'rent_amount', 'down_payment',
        'total', 'paid', 'deposit', 'deposit_paid', 'deposit_status', 'status', 'terminated_at', 'terms', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d', 'terminated_at' => 'date:Y-m-d',
            'rent_amount' => 'decimal:3', 'down_payment' => 'decimal:3', 'total' => 'decimal:3', 'paid' => 'decimal:3',
            'deposit' => 'decimal:3', 'deposit_paid' => 'decimal:3',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(RentalAsset::class, 'asset_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(RentalInstallment::class, 'contract_id')->orderBy('seq');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RentalPayment::class, 'contract_id')->latest('date')->latest('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
