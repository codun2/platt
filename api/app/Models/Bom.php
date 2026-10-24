<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bom extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'item_id', 'name', 'output_qty', 'extra_cost', 'instructions', 'is_active'];

    protected function casts(): array { return ['output_qty' => 'decimal:3', 'extra_cost' => 'decimal:3', 'is_active' => 'boolean']; }

    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
    public function lines(): HasMany { return $this->hasMany(BomLine::class)->orderBy('sort'); }
    public function orders(): HasMany { return $this->hasMany(ProductionOrder::class); }
}
