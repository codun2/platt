<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelPackage extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'type', 'price', 'includes', 'is_active'];

    protected function casts(): array
    {
        return ['price' => 'decimal:3', 'is_active' => 'boolean'];
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class, 'package_id');
    }
}
