<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripExpense extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'trip_id', 'category', 'amount', 'date', 'note'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:3', 'date' => 'date:Y-m-d'];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
