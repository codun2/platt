<?php

/*
|--------------------------------------------------------------------------
| Sector identity
|--------------------------------------------------------------------------
| Gives every sector preset its own look in the app, applied on top of the
| shared module registry by SidebarBuilder (so existing tenants pick it up
| without a migration):
|
|   accent  — the tenant's primary colour (sidebar, buttons, highlights)
|   order   — top-level modules in the order they appear (after the
|             dashboard). Unlisted enabled modules follow; settings is last.
|   labels  — module_key => [ar, en] or [ar, en, icon] contextual naming
|   hide    — module keys (top-level or child) left out of this sector's menu
|
| Only presentation lives here: enabled modules and permissions are still
| decided by tenant_modules + roles.
*/

$shop = ['crm_quotes'];

return [
    'default' => [
        'accent' => '#e11d48',
        'order' => ['crm', 'accounting', 'hr', 'whatsapp'],
        'labels' => [],
        'hide' => [],
    ],

    'general' => [
        'accent' => '#e11d48',
        'order' => ['crm', 'accounting', 'hr', 'whatsapp'],
    ],

    'accounting_firm' => [
        'accent' => '#0f766e',
        'order' => ['accounting', 'crm', 'hr', 'whatsapp'],
        'labels' => [
            'crm' => ['العملاء والخدمات', 'Clients & Billing'],
            'crm_customers' => ['الشركات العميلة', 'Client Companies', 'ti-building'],
            'crm_invoices' => ['فواتير الخدمات', 'Service Invoices'],
            'crm_quotes' => ['عروض الخدمات', 'Service Proposals'],
        ],
    ],

    // ---------------------------------------------------------- retail & trade
    'retail' => [
        'accent' => '#e11d48',
        'order' => ['pos', 'inventory', 'crm', 'accounting', 'hr', 'whatsapp'],
        'labels' => [
            'pos' => ['المبيعات والكاشير', 'Sales & Checkout', 'ti-cash-register'],
            'pos_screen' => ['الكاشير', 'Checkout'],
            'inventory' => ['المخزون والأصناف', 'Stock & Items'],
        ],
        'hide' => $shop,
    ],

    'wholesale' => [
        'accent' => '#4f46e5',
        'order' => ['inventory', 'crm', 'accounting', 'hr', 'whatsapp'],
        'labels' => [
            'inventory' => ['المخازن والبضائع', 'Warehouses & Goods', 'ti-building-warehouse'],
            'inventory_items' => ['البضائع', 'Goods'],
            'crm' => ['عملاء الجملة والفواتير', 'Wholesale Clients'],
            'crm_customers' => ['عملاء الجملة', 'Wholesale Clients'],
            'crm_quotes' => ['عروض أسعار الجملة', 'Wholesale Quotes'],
        ],
    ],

    'car_showroom' => [
        'accent' => '#1d4ed8',
        'order' => ['rental', 'crm', 'inventory', 'accounting', 'hr', 'whatsapp'],
        'labels' => [
            'rental' => ['السيارات والتقسيط', 'Cars & Installments', 'ti-car'],
            'rental_assets' => ['السيارات المعروضة', 'Showroom Cars', 'ti-car'],
            'rental_contracts' => ['عقود التقسيط', 'Installment Contracts'],
            'rental_installments' => ['الأقساط والتحصيل', 'Installments'],
            'inventory' => ['قطع الغيار والإكسسوارات', 'Parts & Accessories', 'ti-engine'],
            'crm' => ['المشترون والفواتير', 'Buyers & Invoices'],
            'crm_customers' => ['المشترون', 'Buyers'],
            'crm_quotes' => ['عروض أسعار السيارات', 'Car Quotes'],
        ],
        // Cars are managed as rental assets (sold on installment contracts);
        // the separate vehicles placeholder adds nothing yet.
        'hide' => ['vehicles', 'rental_owners'],
    ],

    'sweets_shop' => [
        'accent' => '#db2777',
        'order' => ['pos', 'inventory', 'crm', 'accounting', 'hr', 'whatsapp'],
        'labels' => [
            'pos' => ['المبيعات والكاشير', 'Sales & Checkout', 'ti-cash-register'],
            'pos_screen' => ['الكاشير', 'Checkout'],
            'inventory' => ['الحلويات والمخزون', 'Sweets & Stock', 'ti-cake'],
            'inventory_items' => ['أصناف الحلويات', 'Sweets'],
        ],
        'hide' => $shop,
    ],

    'tailoring' => [
        'accent' => '#9333ea',
        'order' => ['service', 'pos', 'inventory', 'crm', 'accounting', 'hr', 'whatsapp'],
        'labels' => [
            'service' => ['طلبات التفصيل', 'Tailoring Orders', 'ti-needle-thread'],
            'service_tickets' => ['طلبات الخياطة', 'Orders'],
            'service_technicians' => ['الخيّاطون', 'Tailors', 'ti-scissors'],
            'pos' => ['المبيعات والكاشير', 'Sales & Checkout'],
            'pos_screen' => ['الكاشير', 'Checkout'],
            'inventory' => ['الأقمشة والمخزون', 'Fabrics & Stock', 'ti-shirt'],
            'inventory_items' => ['الأقمشة والإكسسوارات', 'Fabrics & Trims'],
        ],
        'hide' => [...$shop, 'service_parts'],
    ],

    // ---------------------------------------------------------- hospitality
    'hotel' => [
        'accent' => '#0369a1',
        'order' => ['bookings', 'crm', 'accounting', 'hr', 'whatsapp'],
        'labels' => [
            'bookings' => ['الإقامات والحجوزات', 'Stays & Reservations', 'ti-bed'],
            'bookings_list' => ['الحجوزات', 'Reservations'],
            'bookings_calendar' => ['خريطة الإشغال', 'Occupancy'],
            'bookings_units' => ['الغرف', 'Rooms', 'ti-bed'],
            'bookings_services' => ['خدمات النزلاء', 'Guest Services'],
            'crm' => ['النزلاء والفواتير', 'Guests & Invoices'],
            'crm_customers' => ['النزلاء', 'Guests'],
        ],
        'hide' => $shop,
    ],

    'chalets' => [
        'accent' => '#0891b2',
        'order' => ['bookings', 'crm', 'accounting', 'hr', 'whatsapp'],
        'labels' => [
            'bookings' => ['حجوزات الشاليهات', 'Chalet Bookings', 'ti-beach'],
            'bookings_units' => ['الشاليهات', 'Chalets', 'ti-beach'],
            'bookings_services' => ['الخدمات الإضافية', 'Add-ons'],
        ],
        'hide' => $shop,
    ],

    'halls' => [
        'accent' => '#be185d',
        'order' => ['bookings', 'crm', 'accounting', 'hr', 'whatsapp'],
        'labels' => [
            'bookings' => ['المناسبات والحجوزات', 'Events & Bookings', 'ti-confetti'],
            'bookings_list' => ['حجوزات المناسبات', 'Event Bookings'],
            'bookings_calendar' => ['تقويم المناسبات', 'Events Calendar'],
            'bookings_units' => ['القاعات', 'Halls', 'ti-building-arch'],
            'bookings_services' => ['خدمات المناسبات', 'Event Services'],
        ],
        'hide' => $shop,
    ],

    'travel' => [
        'accent' => '#047857',
        'order' => ['travel', 'crm', 'accounting', 'hr', 'whatsapp'],
        'labels' => [
            'travel' => ['الرحلات والمعتمرون', 'Trips & Pilgrims', 'ti-plane'],
            'travel_travelers' => ['المسافرون والمعتمرون', 'Travelers'],
            'travel_packages' => ['باقات الرحلات', 'Trip Packages'],
        ],
        // A travel agency works through trips; the generic bookings engine
        // (halls/rooms) only adds noise to its menu.
        'hide' => [...$shop, 'bookings'],
    ],

    // ---------------------------------------------------------- rental
    'car_rental' => [
        'accent' => '#2563eb',
        'order' => ['rental', 'crm', 'accounting', 'hr', 'whatsapp'],
        'labels' => [
            'rental' => ['الأسطول والعقود', 'Fleet & Contracts', 'ti-steering-wheel'],
            'rental_assets' => ['السيارات (الأسطول)', 'Fleet', 'ti-car'],
            'rental_contracts' => ['عقود الإيجار', 'Rental Contracts'],
            'rental_installments' => ['الدفعات والتحصيل', 'Payments'],
            'crm' => ['المستأجرون والفواتير', 'Renters & Invoices'],
            'crm_customers' => ['المستأجرون', 'Renters'],
        ],
        'hide' => [...$shop, 'rental_owners'],
    ],

    'real_estate' => [
        'accent' => '#b45309',
        'order' => ['rental', 'crm', 'accounting', 'hr', 'whatsapp'],
        'labels' => [
            'rental' => ['العقارات والإيجارات', 'Properties & Leases', 'ti-building-community'],
            'rental_assets' => ['العقارات والوحدات', 'Properties & Units', 'ti-home'],
            'rental_contracts' => ['عقود الإيجار', 'Leases'],
            'rental_installments' => ['الإيجارات والتحصيل', 'Rent Collection'],
            'rental_owners' => ['الملّاك والوسطاء', 'Owners & Brokers'],
            'crm' => ['المستأجرون والفواتير', 'Tenants & Invoices'],
            'crm_customers' => ['المستأجرون', 'Tenants'],
        ],
        'hide' => $shop,
    ],

    // ---------------------------------------------------------- services
    'auto_garage' => [
        'accent' => '#ea580c',
        'order' => ['service', 'inventory', 'crm', 'accounting', 'hr', 'whatsapp'],
        'labels' => [
            'service' => ['الورشة', 'Workshop', 'ti-car-garage'],
            'service_tickets' => ['أوامر الإصلاح', 'Repair Orders'],
            'inventory' => ['مخزن القطع', 'Parts Store', 'ti-engine'],
            'inventory_items' => ['قطع الغيار', 'Parts'],
            'crm_customers' => ['أصحاب السيارات', 'Car Owners'],
            'crm_quotes' => ['تقديرات الإصلاح', 'Repair Estimates'],
        ],
        // Parts are stocked through the inventory module here.
        'hide' => ['service_parts'],
    ],

    'laundry' => [
        'accent' => '#0284c7',
        'order' => ['service', 'pos', 'crm', 'accounting', 'hr', 'whatsapp'],
        'labels' => [
            'service' => ['طلبات الغسيل', 'Laundry Orders', 'ti-wash-machine'],
            'service_tickets' => ['الطلبات', 'Orders'],
            'service_technicians' => ['العمّال', 'Staff'],
            'pos' => ['الاستلام والكاشير', 'Counter & Checkout'],
            'pos_screen' => ['الكاشير', 'Checkout'],
        ],
        'hide' => [...$shop, 'service_parts'],
    ],

    'maintenance' => [
        'accent' => '#a16207',
        'order' => ['service', 'crm', 'accounting', 'hr', 'whatsapp'],
        'labels' => [
            'service' => ['الصيانة والبلاغات', 'Maintenance & Tickets', 'ti-hammer'],
            'service_tickets' => ['بلاغات الصيانة', 'Tickets'],
            'service_parts' => ['المواد وقطع الغيار', 'Materials & Parts'],
            'crm_quotes' => ['عروض الصيانة', 'Maintenance Quotes'],
        ],
    ],

    // ---------------------------------------------------------- professional
    'law_firm' => [
        'accent' => '#1e3a8a',
        'order' => ['projects', 'crm', 'accounting', 'hr', 'whatsapp'],
        'labels' => [
            'projects' => ['القضايا والجلسات', 'Cases & Hearings', 'ti-scale'],
            'projects_cases' => ['القضايا', 'Cases'],
            'projects_tasks' => ['الجلسات والمهام', 'Hearings & Tasks'],
            'projects_timesheets' => ['الساعات المحتسبة', 'Billable Hours'],
            'crm' => ['الموكّلون والأتعاب', 'Clients & Fees'],
            'crm_customers' => ['الموكّلون', 'Clients'],
            'crm_invoices' => ['فواتير الأتعاب', 'Fee Invoices'],
        ],
        'hide' => [...$shop, 'projects_list'],
    ],

    'engineering' => [
        'accent' => '#0e7490',
        'order' => ['projects', 'crm', 'accounting', 'hr', 'whatsapp'],
        'labels' => [
            'projects' => ['المشاريع الهندسية', 'Engineering Projects', 'ti-ruler-2'],
            'crm_quotes' => ['العروض الفنية والمالية', 'Proposals'],
        ],
        'hide' => ['projects_cases'],
    ],

    'school' => [
        'accent' => '#7c3aed',
        'order' => ['education', 'crm', 'hr', 'accounting', 'whatsapp'],
        'labels' => [
            'education' => ['شؤون الطلاب', 'Student Affairs', 'ti-school'],
            'education_attendance' => ['حضور الطلاب', 'Student Attendance'],
            'crm' => ['أولياء الأمور والرسوم', 'Guardians & Fees', 'ti-users-group'],
            'crm_customers' => ['أولياء الأمور', 'Guardians'],
            'crm_invoices' => ['فواتير الرسوم', 'Fee Invoices'],
            'hr' => ['الكادر التعليمي', 'Staff', 'ti-id-badge'],
            'hr_employees' => ['المعلمون والموظفون', 'Teachers & Staff'],
            'hr_attendance' => ['حضور الكادر والإجازات', 'Staff Attendance'],
        ],
        'hide' => $shop,
    ],

    // ---------------------------------------------------------- production
    'sweets_factory' => [
        'accent' => '#c026d3',
        'order' => ['manufacturing', 'inventory', 'crm', 'accounting', 'hr', 'whatsapp'],
        'labels' => [
            'manufacturing' => ['الإنتاج', 'Production', 'ti-cake'],
            'manufacturing_bom' => ['وصفات الحلويات', 'Recipes'],
            'inventory' => ['الخامات والمنتجات', 'Materials & Products'],
            'inventory_items' => ['الخامات والمنتجات', 'Materials & Products'],
            'crm' => ['الموزّعون والفواتير', 'Distributors & Invoices'],
            'crm_customers' => ['الموزّعون والعملاء', 'Distributors'],
        ],
    ],

    'manufacturing' => [
        'accent' => '#475569',
        'order' => ['manufacturing', 'inventory', 'crm', 'accounting', 'hr', 'whatsapp'],
        'labels' => [
            'manufacturing' => ['الإنتاج', 'Production'],
            'manufacturing_bom' => ['قوائم المواد (BOM)', 'Bills of Materials'],
            'inventory' => ['المواد الخام والمخزون', 'Raw Materials & Stock'],
        ],
    ],

    'farm' => [
        'accent' => '#15803d',
        'order' => ['inventory', 'pos', 'crm', 'accounting', 'hr', 'whatsapp'],
        'labels' => [
            'inventory' => ['المزرعة والمحاصيل', 'Farm & Crops', 'ti-plant-2'],
            'inventory_items' => ['المحاصيل والمنتجات', 'Crops & Produce', 'ti-seeding'],
            'inventory_warehouses' => ['المخازن والحقول', 'Stores & Fields', 'ti-building-warehouse'],
            'inventory_purchases' => ['المستلزمات الزراعية والموردون', 'Farm Supplies', 'ti-truck-delivery'],
            'inventory_movements' => ['الحصاد والحركات', 'Harvest & Movements'],
            'pos' => ['بيع المحصول', 'Produce Sales', 'ti-basket'],
            'pos_screen' => ['شاشة البيع', 'Sell'],
            'crm' => ['التجار والفواتير', 'Traders & Invoices'],
            'crm_customers' => ['التجار والعملاء', 'Traders'],
            'hr' => ['العمال', 'Workers', 'ti-users'],
            'hr_employees' => ['العمال', 'Workers'],
            'hr_attendance' => ['حضور العمال والإجازات', 'Worker Attendance'],
            'hr_payroll' => ['أجور العمال', 'Wages'],
        ],
        'hide' => $shop,
    ],
];
