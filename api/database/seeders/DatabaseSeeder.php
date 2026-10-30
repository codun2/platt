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
        ]);
        // Demo tenants with public test passwords: never on production unless asked for.
        if (filter_var(env('SEED_DEMO', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->call([DemoTenantSeeder::class, SectorDemoSeeder::class]);
        }
    }
}
