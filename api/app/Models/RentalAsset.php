<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RentalAsset extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'parent_id', 'owner_id', 'code', 'name', 'type', 'status', 'city', 'address', 'area', 'rooms', 'bathrooms',
        'floor', 'furnished', 'electricity_meter', 'water_meter', 'plate_no', 'make', 'model', 'year', 'color', 'vin', 'odometer',
        'rent_amount', 'rent_frequency', 'deposit', 'description',
    ];

    protected function casts(): array
    {
        return ['area' => 'decimal:2', 'rent_amount' => 'decimal:3', 'deposit' => 'decimal:3', 'furnished' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(RentalAsset::class, 'parent_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(RentalAsset::class, 'parent_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(RentalOwner::class, 'owner_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(RentalContract::class, 'asset_id');
    }

    public function activeContract(): HasOne
    {
        return $this->hasOne(RentalContract::class, 'asset_id')->where('status', 'active')->latestOfMany();
    }
}
