<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingService extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'name_en', 'price', 'unit_id', 'is_active'];

    protected function casts(): array
    {
        return ['price' => 'decimal:3', 'is_active' => 'boolean'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(BookingUnit::class, 'unit_id');
    }
}
