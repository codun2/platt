<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use BelongsToTenant, HasApiTokens, Notifiable;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'phone', 'password', 'is_super_admin', 'is_owner', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_owner' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /** All permission keys this user holds (owners/super-admins hold everything). */
    public function permissionKeys(): array
    {
        if ($this->is_super_admin || $this->is_owner) {
            return Permission::pluck('key')->all();
        }

        return $this->roles()
            ->with('permissions:id,key')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('key'))
            ->unique()
            ->values()
            ->all();
    }

    public function hasPermission(string $key): bool
    {
        if ($this->is_super_admin || $this->is_owner) {
            return true;
        }

        return in_array($key, $this->permissionKeys(), true);
    }
}
