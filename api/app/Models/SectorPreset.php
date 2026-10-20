<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SectorPreset extends Model
{
    protected $fillable = ['key', 'name_ar', 'name_en', 'icon', 'engine', 'description_ar', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'preset_modules');
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }
}
