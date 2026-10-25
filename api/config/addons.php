<?php

/*
 * Advanced add-ons: modules on the "addons" engine. They belong to no sector
 * preset, so no tenant has them until the platform admin enables them
 * (Super › المشتركون › الإضافات المتقدمة). Text shown to the admin.
 */
return [
    'loyalty' => [
        'ar' => 'نقاط ولاء على كل عملية شراء تُستبدل خصماً عند الكاشير، ومحفظة رصيد مسبق للعميل (شحن، دفع من الرصيد، استرداد) مع كشف حركات لكل عميل.',
        'en' => 'Loyalty points on every purchase, redeemed as a discount at the till, plus a prepaid customer wallet (top-up, pay from balance, refund) with a per-customer statement.',
        'fits' => ['محلات', 'حلويات', 'مطاعم ومقاهي', 'مغاسل', 'صالونات'],
    ],
];
