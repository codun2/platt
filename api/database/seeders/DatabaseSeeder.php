<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Runs on every container boot — every seeder here must be idempotent. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            ModuleSeeder::class,
            PermissionSeeder::class,
            SectorPresetSeeder::class,
            SuperAdminSeeder::class,
            DemoTenantSeeder::class,
            SectorDemoSeeder::class,
        ]);
    }
}
