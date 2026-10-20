<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * One `<module>.view` permission per module (gates the sidebar) plus a few
 * management permissions. Idempotent — safe to re-run after adding modules.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $management = [
            ['key' => 'settings.manage', 'name_ar' => 'إدارة إعدادات الشركة', 'group_key' => 'core'],
            ['key' => 'users.manage', 'name_ar' => 'إدارة المستخدمين', 'group_key' => 'core'],
            ['key' => 'roles.manage', 'name_ar' => 'إدارة الأدوار والصلاحيات', 'group_key' => 'core'],
        ];
        foreach ($management as $p) {
            Permission::updateOrCreate(['key' => $p['key']], $p);
        }

        foreach (Module::orderBy('sort')->get() as $m) {
            Permission::updateOrCreate(
                ['key' => $m->viewPermissionKey()],
                ['name_ar' => 'عرض: '.$m->name_ar, 'group_key' => $m->parent_key ?: $m->key],
            );
        }
    }
}
