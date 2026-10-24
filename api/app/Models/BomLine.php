<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomLine extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'bom_id', 'item_id', 'qty', 'waste_pct', 'sort'];

    protected function casts(): array { return ['qty' => 'decimal:3', 'waste_pct' => 'decimal:2']; }

    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
}
