<?php

/*
 * Advanced add-ons: modules on the "addons" engine. They belong to no sector
 * preset, so no tenant has them until the platform admin enables them
 * (Super › المشتركون › الإضافات المتقدمة). Text shown to the admin.
 */
return [
    'orders' => [
        'ar' => 'طلبات تُستلم اليوم لموعد لاحق (كيك، صواني، ضيافة، طلبيات جملة) مع عربون، ولوحة تحضير (جديد ← قيد التحضير ← جاهز ← تم التسليم)، واستلام من المحل أو توصيل برسوم وسائق، وتذكرة تحضير مطبوعة. عند التسليم تُصدر الفاتورة ويُخصم المخزون ويُحتسب العربون تلقائياً.',
        'en' => 'Orders taken now for a later date (cakes, trays, catering, bulk) with a deposit, a preparation board (new → preparing → ready → delivered), pickup or delivery with fee and driver, and a printed prep ticket. Handing over issues the invoice, deducts stock and applies the deposit automatically.',
        'fits' => ['حلويات', 'مخابز', 'مطاعم وضيافة', 'ورود وهدايا', 'محلات جملة'],
    ],
    'loyalty' => [
        'ar' => 'نقاط ولاء على كل عملية شراء تُستبدل خصماً عند الكاشير، ومحفظة رصيد مسبق للعميل (شحن، دفع من الرصيد، استرداد) مع كشف حركات لكل عميل.',
        'en' => 'Loyalty points on every purchase, redeemed as a discount at the till, plus a prepaid customer wallet (top-up, pay from balance, refund) with a per-customer statement.',
        'fits' => ['محلات', 'حلويات', 'مطاعم ومقاهي', 'مغاسل', 'صالونات'],
    ],
];
