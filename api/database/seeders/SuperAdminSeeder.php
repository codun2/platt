<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/** Platform super-admin. Credentials come from env (SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD). */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL', 'admin@rakeez.app');

        $user = User::withoutGlobalScopes()->where('email', $email)->first();
        if (! $user) {
            User::create([
                'tenant_id' => null,
                'name' => 'مدير المنصّة',
                'email' => $email,
                'password' => env('SUPER_ADMIN_PASSWORD', 'Rakeez@2026'),
                'is_super_admin' => true,
                'is_active' => true,
            ]);
        } elseif (! $user->is_super_admin) {
            $user->forceFill(['is_super_admin' => true, 'tenant_id' => null])->save();
        }
    }
}
