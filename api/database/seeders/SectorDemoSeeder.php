<?php

namespace Database\Seeders;

use App\Models\SectorPreset;
use App\Models\User;
use App\Services\TenantRegistrar;
use Illuminate\Database\Seeder;

/**
 * One test tenant per sector preset so every sector can be reviewed:
 *   <preset_key>@test.app / Test@1234   (e.g. hotel@test.app)
 * Idempotent — skips tenants whose owner e-mail already exists.
 */
class SectorDemoSeeder extends Seeder
{
    public const PASSWORD = 'Test@1234';

    public function run(): void
    {
        $registrar = app(TenantRegistrar::class);

        foreach (SectorPreset::where('is_active', true)->orderBy('sort')->get() as $p) {
            $email = $p->key.'@test.app';
            if (User::withoutGlobalScopes()->where('email', $email)->exists()) {
                continue;
            }
            $registrar->register([
                'company_name' => $p->name_ar.' — تجريبي',
                'sector' => $p->key,
                'name' => 'مدير '.$p->name_ar,
                'email' => $email,
                'phone' => '9689'.str_pad((string) ($p->sort * 7 % 10000), 4, '0', STR_PAD_LEFT),
                'password' => self::PASSWORD,
            ]);
        }
    }
}
