<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\SectorPreset;
use Illuminate\Database\Seeder;

/** ~20 sectors → which top-level modules each preset turns on. Idempotent. */
class SectorPresetSeeder extends Seeder
{
    public function run(): void
    {
        $sort = 0;
        foreach ($this->presets() as $p) {
            $moduleKeys = $p['modules'];
            unset($p['modules']);
            $p['sort'] = $sort += 10;

            $preset = SectorPreset::updateOrCreate(['key' => $p['key']], $p + ['is_active' => true]);

            // Enable the listed top-level modules plus all of their children.
            $ids = Module::whereIn('key', $moduleKeys)->orWhereIn('parent_key', $moduleKeys)->pluck('id');
            $preset->modules()->syncWithoutDetaching($ids);
        }
    }

    private function presets(): array
    {
        $base = ['accounting', 'crm', 'hr', 'whatsapp'];

        return [
            ['key' => 'general', 'name_ar' => 'أعمال عامة', 'name_en' => 'General Business', 'icon' => 'ti-building', 'engine' => 'core', 'description_ar' => 'محاسبة + عملاء وفواتير + موارد بشرية', 'modules' => $base],
            ['key' => 'accounting_firm', 'name_ar' => 'محاسبة شركات', 'name_en' => 'Accounting Firm', 'icon' => 'ti-calculator', 'engine' => 'core', 'description_ar' => 'محاسبة متقدمة وتقارير مالية', 'modules' => $base],
            ['key' => 'retail', 'name_ar' => 'متاجر ونقاط بيع', 'name_en' => 'Retail & POS', 'icon' => 'ti-shopping-cart', 'engine' => 'inventory', 'description_ar' => 'أصناف ومخزون وفواتير بيع ونقطة بيع', 'modules' => [...$base, 'inventory', 'pos']],
            ['key' => 'wholesale', 'name_ar' => 'مبيعات ومخازن', 'name_en' => 'Sales & Warehouses', 'icon' => 'ti-building-warehouse', 'engine' => 'inventory', 'description_ar' => 'مستودعات متعددة ومشتريات وموردون', 'modules' => [...$base, 'inventory']],
            ['key' => 'car_showroom', 'name_ar' => 'معارض سيارات', 'name_en' => 'Car Showroom', 'icon' => 'ti-car', 'engine' => 'inventory', 'description_ar' => 'سيارات للبيع + فواتير + أقساط', 'modules' => [...$base, 'inventory', 'vehicles', 'rental']],
            ['key' => 'sweets_shop', 'name_ar' => 'محلات حلوى', 'name_en' => 'Sweets Shop', 'icon' => 'ti-candy', 'engine' => 'inventory', 'description_ar' => 'نقطة بيع ومخزون', 'modules' => [...$base, 'inventory', 'pos']],
            ['key' => 'tailoring', 'name_ar' => 'خياطة', 'name_en' => 'Tailoring', 'icon' => 'ti-needle-thread', 'engine' => 'inventory', 'description_ar' => 'طلبات خياطة + مخزون أقمشة + نقطة بيع', 'modules' => [...$base, 'inventory', 'pos', 'service']],
            ['key' => 'hotel', 'name_ar' => 'فنادق', 'name_en' => 'Hotels', 'icon' => 'ti-bed', 'engine' => 'bookings', 'description_ar' => 'غرف وتقويم حجوزات', 'modules' => [...$base, 'bookings']],
            ['key' => 'chalets', 'name_ar' => 'شاليهات', 'name_en' => 'Chalets', 'icon' => 'ti-beach', 'engine' => 'bookings', 'description_ar' => 'وحدات وحجوزات وتأمينات', 'modules' => [...$base, 'bookings']],
            ['key' => 'halls', 'name_ar' => 'قاعات ومناسبات', 'name_en' => 'Halls & Events', 'icon' => 'ti-confetti', 'engine' => 'bookings', 'description_ar' => 'قاعات وخدمات إضافية وحجوزات', 'modules' => [...$base, 'bookings']],
            ['key' => 'travel', 'name_ar' => 'حج وعمرة وسفر', 'name_en' => 'Hajj, Umrah & Travel', 'icon' => 'ti-plane', 'engine' => 'bookings', 'description_ar' => 'رحلات ومسافرون وباقات', 'modules' => [...$base, 'travel', 'bookings']],
            ['key' => 'car_rental', 'name_ar' => 'تأجير سيارات', 'name_en' => 'Car Rental', 'icon' => 'ti-steering-wheel', 'engine' => 'rental', 'description_ar' => 'أسطول + عقود + أقساط', 'modules' => [...$base, 'rental']],
            ['key' => 'real_estate', 'name_ar' => 'عقارات وأملاك', 'name_en' => 'Real Estate', 'icon' => 'ti-building-skyscraper', 'engine' => 'rental', 'description_ar' => 'بنايات ووحدات وعقود إيجار وأقساط', 'modules' => [...$base, 'rental']],
            ['key' => 'auto_garage', 'name_ar' => 'ورش صيانة سيارات', 'name_en' => 'Auto Garage', 'icon' => 'ti-tool', 'engine' => 'service', 'description_ar' => 'أوامر عمل وفنيون وقطع غيار', 'modules' => [...$base, 'service', 'inventory']],
            ['key' => 'laundry', 'name_ar' => 'مغاسل', 'name_en' => 'Laundry', 'icon' => 'ti-wash-machine', 'engine' => 'service', 'description_ar' => 'طلبات غسيل وتتبّع الحالة', 'modules' => [...$base, 'service', 'pos']],
            ['key' => 'maintenance', 'name_ar' => 'شركات صيانة', 'name_en' => 'Maintenance', 'icon' => 'ti-hammer', 'engine' => 'service', 'description_ar' => 'بلاغات صيانة وفنيون', 'modules' => [...$base, 'service']],
            ['key' => 'solar', 'name_ar' => 'الطاقة الشمسية', 'name_en' => 'Solar Energy', 'icon' => 'ti-solar-panel-2', 'engine' => 'service', 'description_ar' => 'تركيب وصيانة أنظمة الطاقة + مخزون الألواح والمعدات + عروض أسعار + بيع قطع', 'modules' => [...$base, 'inventory', 'service', 'pos']],
            ['key' => 'law_firm', 'name_ar' => 'محاماة', 'name_en' => 'Law Firm', 'icon' => 'ti-gavel', 'engine' => 'projects', 'description_ar' => 'قضايا وجلسات وأتعاب', 'modules' => [...$base, 'projects']],
            ['key' => 'engineering', 'name_ar' => 'استشارات هندسية', 'name_en' => 'Engineering Consultancy', 'icon' => 'ti-ruler-2', 'engine' => 'projects', 'description_ar' => 'مشاريع ومهام وساعات عمل', 'modules' => [...$base, 'projects']],
            ['key' => 'school', 'name_ar' => 'مدارس ورياض أطفال', 'name_en' => 'Schools', 'icon' => 'ti-school', 'engine' => 'education', 'description_ar' => 'طلاب وصفوف ورسوم وحضور', 'modules' => [...$base, 'education']],
            ['key' => 'sweets_factory', 'name_ar' => 'مصانع حلوى', 'name_en' => 'Sweets Factory', 'icon' => 'ti-building-factory-2', 'engine' => 'manufacturing', 'description_ar' => 'وصفات وأوامر إنتاج ومخزون', 'modules' => [...$base, 'manufacturing', 'inventory']],
            ['key' => 'manufacturing', 'name_ar' => 'تصنيع', 'name_en' => 'Manufacturing', 'icon' => 'ti-settings-automation', 'engine' => 'manufacturing', 'description_ar' => 'إنتاج ومواد خام ومخزون', 'modules' => [...$base, 'manufacturing', 'inventory']],
            ['key' => 'farm', 'name_ar' => 'مزارع', 'name_en' => 'Farms', 'icon' => 'ti-plant', 'engine' => 'inventory', 'description_ar' => 'محاصيل ومخزون ومبيعات', 'modules' => [...$base, 'inventory', 'pos']],
        ];
    }
}
