<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    protected $fillable = [
        'key', 'name_ar', 'name_en', 'icon', 'route', 'parent_key', 'engine', 'sort', 'is_core', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_core' => 'boolean', 'is_active' => 'boolean'];
    }

    public function children(): HasMany
    {
        return $this->hasMany(Module::class, 'parent_key', 'key')->orderBy('sort');
    }

    /** Permission key that gates visibility of this module in the sidebar. */
    public function viewPermissionKey(): string
    {
        return $this->key.'.view';
    }
}
