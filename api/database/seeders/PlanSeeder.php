<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['key' => 'basic', 'name_ar' => 'الأساسية', 'name_en' => 'Basic', 'price_monthly' => 15, 'max_users' => 3, 'sort' => 10, 'description_ar' => 'للمنشآت الصغيرة — حتى 3 مستخدمين'],
            ['key' => 'pro', 'name_ar' => 'الاحترافية', 'name_en' => 'Pro', 'price_monthly' => 35, 'max_users' => 10, 'sort' => 20, 'description_ar' => 'للشركات المتوسطة — حتى 10 مستخدمين'],
            ['key' => 'enterprise', 'name_ar' => 'المؤسسات', 'name_en' => 'Enterprise', 'price_monthly' => 80, 'max_users' => 50, 'sort' => 30, 'description_ar' => 'للشركات الكبيرة — حتى 50 مستخدماً'],
        ];

        foreach ($plans as $p) {
            Plan::updateOrCreate(['key' => $p['key']], $p + ['is_active' => true]);
        }
    }
}
