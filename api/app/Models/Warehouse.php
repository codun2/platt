<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'location', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean'];
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /** The tenant's default warehouse (created lazily so POS always has one). */
    public static function defaultFor(): self
    {
        return static::where('is_default', true)->first()
            ?? static::orderBy('id')->first()
            ?? static::create(['name' => 'المستودع الرئيسي', 'is_default' => true]);
    }
}
