<?php

/*
 * Starter data seeded once when a tenant of a given sector is created
 * (TenantRegistrar), so the account is useful from the first login.
 * categories: inventory categories (only used when the inventory module is on).
 */
return [
    'solar' => [
        'categories' => ['الألواح الشمسية', 'الإنفرترات', 'البطاريات', 'هياكل التثبيت', 'الكابلات والملحقات', 'أنظمة المراقبة'],
    ],
    'sweets_shop' => [
        'categories' => ['حلويات', 'شوكولاتة', 'مكسرات', 'مشروبات', 'تغليف وهدايا'],
    ],
    'sweets_factory' => [
        'categories' => ['مواد خام', 'منتجات جاهزة', 'تغليف'],
    ],
    'farm' => [
        'categories' => ['محاصيل', 'بذور وشتلات', 'أسمدة ومبيدات', 'أعلاف'],
    ],
];
