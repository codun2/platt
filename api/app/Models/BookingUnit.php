<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingUnit extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'name_en', 'type', 'capacity', 'price_day', 'price_night', 'price_hour', 'deposit', 'location', 'color', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['price_day' => 'decimal:3', 'price_night' => 'decimal:3', 'price_hour' => 'decimal:3', 'deposit' => 'decimal:3', 'is_active' => 'boolean'];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'unit_id');
    }
}
