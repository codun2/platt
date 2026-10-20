<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripPayment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'traveler_id', 'amount', 'method', 'date', 'note', 'user_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:3', 'date' => 'date:Y-m-d'];
    }

    public function traveler(): BelongsTo
    {
        return $this->belongsTo(TripTraveler::class, 'traveler_id');
    }
}
