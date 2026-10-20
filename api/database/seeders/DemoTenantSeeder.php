<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\TenantRegistrar;
use Illuminate\Database\Seeder;

/** A demo company (retail preset) so the app can be tried immediately. Runs once. */
class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        if (User::withoutGlobalScopes()->where('email', 'demo@rakeez.app')->exists()) {
            return;
        }

        app(TenantRegistrar::class)->register([
            'company_name' => 'شركة التجربة للتجارة',
            'sector' => 'retail',
            'name' => 'مدير التجربة',
            'email' => 'demo@rakeez.app',
            'phone' => '96890000000',
            'password' => 'demo1234',
        ]);
    }
}
