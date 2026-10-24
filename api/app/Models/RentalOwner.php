<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalOwner extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'type', 'phone', 'email', 'national_id', 'commission_rate', 'bank_name', 'iban', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['commission_rate' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(RentalAsset::class, 'owner_id');
    }
}
