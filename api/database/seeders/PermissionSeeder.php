<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * One `<module>.view` permission per module (gates the sidebar) plus
 * management permissions per engine. Idempotent — safe to re-run after adding
 * modules; every tenant's system `admin` role is re-synced to hold everything.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $management = [
            ['key' => 'settings.manage', 'name_ar' => 'إدارة إعدادات الشركة', 'group_key' => 'core'],
            ['key' => 'users.manage', 'name_ar' => 'إدارة المستخدمين', 'group_key' => 'core'],
            ['key' => 'roles.manage', 'name_ar' => 'إدارة الأدوار والصلاحيات', 'group_key' => 'core'],
            ['key' => 'crm.manage', 'name_ar' => 'إدارة العملاء والفواتير وعروض الأسعار', 'group_key' => 'crm'],
            ['key' => 'accounting.manage', 'name_ar' => 'إدارة الحسابات والقيود والمصروفات', 'group_key' => 'accounting'],
            ['key' => 'hr.manage', 'name_ar' => 'إدارة الموظفين والحضور والرواتب', 'group_key' => 'hr'],
            ['key' => 'inventory.manage', 'name_ar' => 'إدارة الأصناف والمخزون والمشتريات', 'group_key' => 'inventory'],
            ['key' => 'pos.manage', 'name_ar' => 'إلغاء الفواتير', 'group_key' => 'pos'],
            ['key' => 'bookings.manage', 'name_ar' => 'إدارة الوحدات والخدمات', 'group_key' => 'bookings'],
            ['key' => 'manufacturing.manage', 'name_ar' => 'إدارة الوصفات وقوائم المواد', 'group_key' => 'manufacturing'],
            ['key' => 'education.manage', 'name_ar' => 'إدارة الطلاب والصفوف وإصدار الرسوم', 'group_key' => 'education'],
            ['key' => 'service.manage', 'name_ar' => 'إدارة الفنيين وقائمة الأسعار وحذف أوامر العمل', 'group_key' => 'service'],
            ['key' => 'projects.manage', 'name_ar' => 'إدارة القضايا والمشاريع والفوترة', 'group_key' => 'projects'],
            ['key' => 'rental.manage', 'name_ar' => 'إدارة الأصول والعقود والملّاك', 'group_key' => 'rental'],
            ['key' => 'travel.manage', 'name_ar' => 'إدارة الرحلات والباقات والمصروفات', 'group_key' => 'travel'],
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

        // Keep every tenant's admin role complete as new permissions appear.
        $all = Permission::pluck('id');
        Role::withoutGlobalScopes()->where('key', 'admin')->where('is_system', true)->each(fn (Role $r) => $r->permissions()->sync($all));
    }
}
