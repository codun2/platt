<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

/**
 * Module registry — Core + 8 engines (ARCHITECTURE.md §3). Idempotent.
 * Routes point at the Nuxt SPA; engines not yet built render a "قريباً" page.
 */
class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $sort = 0;
        foreach ($this->modules() as $m) {
            $m['sort'] = $m['sort'] ?? ($sort += 10);
            Module::updateOrCreate(['key' => $m['key']], $m + ['is_core' => false, 'is_active' => true]);
        }
    }

    private function modules(): array
    {
        $core = fn (array $m) => $m + ['engine' => 'core', 'is_core' => true];

        return [
            // ---- Core (always on) ----
            $core(['key' => 'dashboard', 'name_ar' => 'الرئيسية', 'name_en' => 'Dashboard', 'icon' => 'ti-layout-dashboard', 'route' => '/']),
            $core(['key' => 'settings', 'name_ar' => 'الإعدادات العامة', 'name_en' => 'Settings', 'icon' => 'ti-settings', 'route' => '/settings']),
            $core(['key' => 'settings_company', 'parent_key' => 'settings', 'name_ar' => 'بيانات الشركة', 'name_en' => 'Company', 'icon' => 'ti-building', 'route' => '/settings/company']),
            $core(['key' => 'settings_users', 'parent_key' => 'settings', 'name_ar' => 'المستخدمون', 'name_en' => 'Users', 'icon' => 'ti-users', 'route' => '/settings/users']),
            $core(['key' => 'settings_roles', 'parent_key' => 'settings', 'name_ar' => 'الأدوار والصلاحيات', 'name_en' => 'Roles', 'icon' => 'ti-shield-lock', 'route' => '/settings/roles']),

            // ---- Core (horizontal, enabled per preset) ----
            ['key' => 'accounting', 'engine' => 'core', 'name_ar' => 'المحاسبة', 'name_en' => 'Accounting', 'icon' => 'ti-calculator', 'route' => '/accounting'],
            ['key' => 'accounting_accounts', 'parent_key' => 'accounting', 'engine' => 'core', 'name_ar' => 'شجرة الحسابات', 'name_en' => 'Chart of Accounts', 'icon' => 'ti-sitemap', 'route' => '/accounting/accounts'],
            ['key' => 'accounting_journal', 'parent_key' => 'accounting', 'engine' => 'core', 'name_ar' => 'القيود اليومية', 'name_en' => 'Journal', 'icon' => 'ti-book', 'route' => '/accounting/journal'],
            ['key' => 'accounting_expenses', 'parent_key' => 'accounting', 'engine' => 'core', 'name_ar' => 'المصروفات', 'name_en' => 'Expenses', 'icon' => 'ti-receipt', 'route' => '/accounting/expenses'],
            ['key' => 'accounting_reports', 'parent_key' => 'accounting', 'engine' => 'core', 'name_ar' => 'التقارير المالية', 'name_en' => 'Reports', 'icon' => 'ti-report-analytics', 'route' => '/accounting/reports'],

            ['key' => 'crm', 'engine' => 'core', 'name_ar' => 'العملاء والفواتير', 'name_en' => 'CRM & Invoices', 'icon' => 'ti-users-group', 'route' => '/crm'],
            ['key' => 'crm_customers', 'parent_key' => 'crm', 'engine' => 'core', 'name_ar' => 'العملاء', 'name_en' => 'Customers', 'icon' => 'ti-user', 'route' => '/crm/customers'],
            ['key' => 'crm_invoices', 'parent_key' => 'crm', 'engine' => 'core', 'name_ar' => 'الفواتير', 'name_en' => 'Invoices', 'icon' => 'ti-file-invoice', 'route' => '/crm/invoices'],
            ['key' => 'crm_quotes', 'parent_key' => 'crm', 'engine' => 'core', 'name_ar' => 'عروض الأسعار', 'name_en' => 'Quotes', 'icon' => 'ti-file-dollar', 'route' => '/crm/quotes'],

            ['key' => 'hr', 'engine' => 'core', 'name_ar' => 'الموارد البشرية', 'name_en' => 'HR', 'icon' => 'ti-id-badge', 'route' => '/hr'],
            ['key' => 'hr_employees', 'parent_key' => 'hr', 'engine' => 'core', 'name_ar' => 'الموظفون', 'name_en' => 'Employees', 'icon' => 'ti-users', 'route' => '/hr/employees'],
            ['key' => 'hr_attendance', 'parent_key' => 'hr', 'engine' => 'core', 'name_ar' => 'الحضور والإجازات', 'name_en' => 'Attendance', 'icon' => 'ti-calendar-check', 'route' => '/hr/attendance'],
            ['key' => 'hr_payroll', 'parent_key' => 'hr', 'engine' => 'core', 'name_ar' => 'الرواتب', 'name_en' => 'Payroll', 'icon' => 'ti-cash', 'route' => '/hr/payroll'],

            ['key' => 'whatsapp', 'engine' => 'core', 'name_ar' => 'أتمتة واتساب', 'name_en' => 'WhatsApp', 'icon' => 'ti-brand-whatsapp', 'route' => '/whatsapp'],

            // ---- Engine: Inventory + POS ----
            ['key' => 'inventory', 'engine' => 'inventory', 'name_ar' => 'المخزون', 'name_en' => 'Inventory', 'icon' => 'ti-package', 'route' => '/inventory'],
            ['key' => 'inventory_items', 'parent_key' => 'inventory', 'engine' => 'inventory', 'name_ar' => 'الأصناف', 'name_en' => 'Items', 'icon' => 'ti-box', 'route' => '/inventory/items'],
            ['key' => 'inventory_warehouses', 'parent_key' => 'inventory', 'engine' => 'inventory', 'name_ar' => 'المستودعات', 'name_en' => 'Warehouses', 'icon' => 'ti-building-warehouse', 'route' => '/inventory/warehouses'],
            ['key' => 'inventory_purchases', 'parent_key' => 'inventory', 'engine' => 'inventory', 'name_ar' => 'المشتريات والموردون', 'name_en' => 'Purchases', 'icon' => 'ti-truck-delivery', 'route' => '/inventory/purchases'],
            ['key' => 'inventory_movements', 'parent_key' => 'inventory', 'engine' => 'inventory', 'name_ar' => 'حركات المخزون', 'name_en' => 'Stock Movements', 'icon' => 'ti-arrows-exchange', 'route' => '/inventory/movements'],
            ['key' => 'pos', 'engine' => 'inventory', 'name_ar' => 'نقطة البيع', 'name_en' => 'POS', 'icon' => 'ti-cash-register', 'route' => '/pos'],
            ['key' => 'pos_screen', 'parent_key' => 'pos', 'engine' => 'inventory', 'name_ar' => 'شاشة البيع', 'name_en' => 'POS Screen', 'icon' => 'ti-device-desktop', 'route' => '/pos'],
            ['key' => 'pos_sales', 'parent_key' => 'pos', 'engine' => 'inventory', 'name_ar' => 'الفواتير', 'name_en' => 'Sales', 'icon' => 'ti-shopping-cart', 'route' => '/pos/sales'],
            ['key' => 'pos_shifts', 'parent_key' => 'pos', 'engine' => 'inventory', 'name_ar' => 'الورديات', 'name_en' => 'Shifts', 'icon' => 'ti-clock', 'route' => '/pos/shifts'],
            ['key' => 'pos_reports', 'parent_key' => 'pos', 'engine' => 'inventory', 'name_ar' => 'تقارير المبيعات', 'name_en' => 'Sales Reports', 'icon' => 'ti-report-analytics', 'route' => '/pos/reports'],
            ['key' => 'vehicles', 'engine' => 'inventory', 'name_ar' => 'معرض السيارات', 'name_en' => 'Vehicles', 'icon' => 'ti-car', 'route' => '/vehicles'],

            // ---- Engine: Bookings ----
            ['key' => 'bookings', 'engine' => 'bookings', 'name_ar' => 'الحجوزات', 'name_en' => 'Bookings', 'icon' => 'ti-calendar-event', 'route' => '/bookings'],
            ['key' => 'bookings_calendar', 'parent_key' => 'bookings', 'engine' => 'bookings', 'name_ar' => 'تقويم الحجوزات', 'name_en' => 'Calendar', 'icon' => 'ti-calendar', 'route' => '/bookings/calendar'],
            ['key' => 'bookings_units', 'parent_key' => 'bookings', 'engine' => 'bookings', 'name_ar' => 'القاعات / الغرف / الوحدات', 'name_en' => 'Units', 'icon' => 'ti-door', 'route' => '/bookings/units'],
            ['key' => 'bookings_services', 'parent_key' => 'bookings', 'engine' => 'bookings', 'name_ar' => 'الخدمات الإضافية', 'name_en' => 'Services', 'icon' => 'ti-sparkles', 'route' => '/bookings/services'],
            ['key' => 'travel', 'engine' => 'bookings', 'name_ar' => 'الحج والعمرة والسفر', 'name_en' => 'Travel', 'icon' => 'ti-plane', 'route' => '/travel'],
            ['key' => 'travel_trips', 'parent_key' => 'travel', 'engine' => 'bookings', 'name_ar' => 'الرحلات', 'name_en' => 'Trips', 'icon' => 'ti-map-pin', 'route' => '/travel/trips'],
            ['key' => 'travel_travelers', 'parent_key' => 'travel', 'engine' => 'bookings', 'name_ar' => 'المسافرون', 'name_en' => 'Travelers', 'icon' => 'ti-users', 'route' => '/travel/travelers'],
            ['key' => 'travel_packages', 'parent_key' => 'travel', 'engine' => 'bookings', 'name_ar' => 'الباقات', 'name_en' => 'Packages', 'icon' => 'ti-package', 'route' => '/travel/packages'],

            // ---- Engine: Rental + contracts + installments ----
            ['key' => 'rental', 'engine' => 'rental', 'name_ar' => 'التأجير والعقود', 'name_en' => 'Rental', 'icon' => 'ti-file-text', 'route' => '/rental'],
            ['key' => 'rental_assets', 'parent_key' => 'rental', 'engine' => 'rental', 'name_ar' => 'الأصول المؤجّرة', 'name_en' => 'Assets', 'icon' => 'ti-building-skyscraper', 'route' => '/rental/assets'],
            ['key' => 'rental_contracts', 'parent_key' => 'rental', 'engine' => 'rental', 'name_ar' => 'العقود', 'name_en' => 'Contracts', 'icon' => 'ti-file-certificate', 'route' => '/rental/contracts'],
            ['key' => 'rental_installments', 'parent_key' => 'rental', 'engine' => 'rental', 'name_ar' => 'الأقساط والتحصيل', 'name_en' => 'Installments', 'icon' => 'ti-coins', 'route' => '/rental/installments'],
            ['key' => 'rental_owners', 'parent_key' => 'rental', 'engine' => 'rental', 'name_ar' => 'الملاك والوسطاء', 'name_en' => 'Owners', 'icon' => 'ti-user-star', 'route' => '/rental/owners'],

            // ---- Engine: Service tickets ----
            ['key' => 'service', 'engine' => 'service', 'name_ar' => 'الصيانة وأوامر العمل', 'name_en' => 'Service', 'icon' => 'ti-tool', 'route' => '/service'],
            ['key' => 'service_tickets', 'parent_key' => 'service', 'engine' => 'service', 'name_ar' => 'أوامر العمل', 'name_en' => 'Work Orders', 'icon' => 'ti-clipboard-list', 'route' => '/service/tickets'],
            ['key' => 'service_technicians', 'parent_key' => 'service', 'engine' => 'service', 'name_ar' => 'الفنيون', 'name_en' => 'Technicians', 'icon' => 'ti-user-cog', 'route' => '/service/technicians'],
            ['key' => 'service_parts', 'parent_key' => 'service', 'engine' => 'service', 'name_ar' => 'قطع الغيار', 'name_en' => 'Parts', 'icon' => 'ti-engine', 'route' => '/service/parts'],

            // ---- Engine: Projects / Cases ----
            ['key' => 'projects', 'engine' => 'projects', 'name_ar' => 'المشاريع والقضايا', 'name_en' => 'Projects', 'icon' => 'ti-briefcase', 'route' => '/projects'],
            ['key' => 'projects_list', 'parent_key' => 'projects', 'engine' => 'projects', 'name_ar' => 'المشاريع', 'name_en' => 'Projects', 'icon' => 'ti-folders', 'route' => '/projects/list'],
            ['key' => 'projects_cases', 'parent_key' => 'projects', 'engine' => 'projects', 'name_ar' => 'القضايا', 'name_en' => 'Cases', 'icon' => 'ti-gavel', 'route' => '/projects/cases'],
            ['key' => 'projects_tasks', 'parent_key' => 'projects', 'engine' => 'projects', 'name_ar' => 'المهام', 'name_en' => 'Tasks', 'icon' => 'ti-checklist', 'route' => '/projects/tasks'],
            ['key' => 'projects_timesheets', 'parent_key' => 'projects', 'engine' => 'projects', 'name_ar' => 'ساعات العمل', 'name_en' => 'Timesheets', 'icon' => 'ti-hourglass', 'route' => '/projects/timesheets'],

            // ---- Engine: Education ----
            ['key' => 'education', 'engine' => 'education', 'name_ar' => 'المدارس', 'name_en' => 'Education', 'icon' => 'ti-school', 'route' => '/education'],
            ['key' => 'education_students', 'parent_key' => 'education', 'engine' => 'education', 'name_ar' => 'الطلاب', 'name_en' => 'Students', 'icon' => 'ti-users', 'route' => '/education/students'],
            ['key' => 'education_classes', 'parent_key' => 'education', 'engine' => 'education', 'name_ar' => 'الصفوف والشعب', 'name_en' => 'Classes', 'icon' => 'ti-chalkboard', 'route' => '/education/classes'],
            ['key' => 'education_fees', 'parent_key' => 'education', 'engine' => 'education', 'name_ar' => 'الرسوم والأقساط', 'name_en' => 'Fees', 'icon' => 'ti-coins', 'route' => '/education/fees'],
            ['key' => 'education_attendance', 'parent_key' => 'education', 'engine' => 'education', 'name_ar' => 'الحضور والغياب', 'name_en' => 'Attendance', 'icon' => 'ti-calendar-check', 'route' => '/education/attendance'],

            // ---- Engine: Manufacturing ----
            ['key' => 'manufacturing', 'engine' => 'manufacturing', 'name_ar' => 'التصنيع', 'name_en' => 'Manufacturing', 'icon' => 'ti-building-factory-2', 'route' => '/manufacturing'],
            ['key' => 'manufacturing_bom', 'parent_key' => 'manufacturing', 'engine' => 'manufacturing', 'name_ar' => 'الوصفات (BOM)', 'name_en' => 'BOM', 'icon' => 'ti-list-details', 'route' => '/manufacturing/bom'],
            ['key' => 'manufacturing_orders', 'parent_key' => 'manufacturing', 'engine' => 'manufacturing', 'name_ar' => 'أوامر الإنتاج', 'name_en' => 'Production Orders', 'icon' => 'ti-settings-automation', 'route' => '/manufacturing/orders'],
        ];
    }
}
