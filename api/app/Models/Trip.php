<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'type', 'destination', 'departure_date', 'return_date', 'capacity', 'price', 'package_id', 'status', 'note'];

    protected function casts(): array
    {
        return ['departure_date' => 'date:Y-m-d', 'return_date' => 'date:Y-m-d', 'price' => 'decimal:3'];
    }

    public function travelers(): HasMany
    {
        return $this->hasMany(TripTraveler::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(TripExpense::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class, 'package_id');
    }
}
