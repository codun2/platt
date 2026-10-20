<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SectorPreset;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registers a new tenant: company + owner user + system roles, then copies the
 * chosen sector preset's modules into tenant_modules.
 */
class TenantRegistrar
{
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $preset = SectorPreset::where('key', $data['sector'])->where('is_active', true)->firstOrFail();
            $plan = Plan::where('is_active', true)->orderBy('sort')->first();

            $tenant = Tenant::create([
                'name' => $data['company_name'],
                'slug' => $this->uniqueSlug($data['company_name']),
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'],
                'plan_id' => $plan?->id,
                'status' => 'active',
                'subscription_status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
                'subscription_renews_at' => now()->addDays(14),
            ]);

            $tenant->applyPreset($preset);

            $owner = User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'is_owner' => true,
                'is_active' => true,
            ]);

            $this->createSystemRoles($tenant, $owner);

            return ['tenant' => $tenant, 'user' => $owner];
        });
    }

    public function createSystemRoles(Tenant $tenant, ?User $owner = null): void
    {
        $admin = Role::firstOrCreate(
            ['tenant_id' => $tenant->id, 'key' => 'admin'],
            ['name_ar' => 'مدير النظام', 'is_system' => true],
        );
        $admin->permissions()->sync(Permission::pluck('id'));

        Role::firstOrCreate(
            ['tenant_id' => $tenant->id, 'key' => 'staff'],
            ['name_ar' => 'موظف', 'is_system' => true],
        );

        if ($owner) {
            $owner->roles()->syncWithoutDetaching([$admin->id]);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tenant';
        $slug = $base;
        $i = 1;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
