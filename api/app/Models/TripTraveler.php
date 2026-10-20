<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripTraveler extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'trip_id', 'customer_id', 'name', 'phone', 'passport_no', 'passport_expiry', 'nationality', 'gender', 'birth_date', 'package_id', 'price', 'paid', 'status', 'notes'];

    protected function casts(): array
    {
        return ['passport_expiry' => 'date:Y-m-d', 'birth_date' => 'date:Y-m-d', 'price' => 'decimal:3', 'paid' => 'decimal:3'];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class, 'package_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TripPayment::class, 'traveler_id');
    }
}
