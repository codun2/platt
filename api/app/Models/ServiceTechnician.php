<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceTechnician extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'phone', 'specialty', 'user_id', 'is_active'];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function orders(): HasMany { return $this->hasMany(ServiceOrder::class, 'technician_id'); }
}
