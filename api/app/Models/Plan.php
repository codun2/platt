<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['key', 'name_ar', 'name_en', 'price_monthly', 'max_users', 'description_ar', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['price_monthly' => 'decimal:3', 'is_active' => 'boolean'];
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }
}
