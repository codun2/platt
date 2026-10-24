<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ServiceCatalog extends Model
{
    use BelongsToTenant;

    protected $table = 'service_catalog';

    protected $fillable = ['tenant_id', 'name', 'kind', 'price', 'cost', 'unit', 'is_active'];

    protected function casts(): array { return ['price' => 'decimal:3', 'cost' => 'decimal:3', 'is_active' => 'boolean']; }
}
