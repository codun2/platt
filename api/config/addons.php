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
    'promotions' => [
        'ar' => 'عروض تُطبَّق تلقائياً عند الكاشير: خصم نسبة أو مبلغ على أصناف أو تصنيفات، اشترِ X واحصل على Y مجاناً، خصم على الفاتورة فوق حد أدنى — بفترة وأيام وساعات محددة (مثل ساعة سعيدة أو عرض نهاية الأسبوع)، مع كوبونات برمز وحد استخدام وتقرير بما أعطاه كل عرض.',
        'en' => 'Offers applied automatically at the till: % or amount off items or categories, buy X get Y free, bill discount above a minimum — limited by dates, weekdays and hours (happy hour, weekend deals), plus coupon codes with usage limits and a report of what each offer gave.',
        'fits' => ['محلات', 'حلويات', 'مطاعم ومقاهي', 'سوبرماركت', 'ملابس'],
    ],
    'storefront' => [
        'ar' => 'رابط خاص للمحل (يُرسل على واتساب وإنستغرام) يعرض الأصناف والأسعار، يطلب منه العميل بدون حساب ويختار استلاماً أو توصيلاً وموعداً — ويصل الطلب مباشرة إلى لوحة الطلبات المسبقة. تتحكم بالأصناف الظاهرة ورسوم التوصيل والحد الأدنى ووقت التجهيز.',
        'en' => 'A shop link (share on WhatsApp / Instagram) listing items and prices; customers order without an account, choose pickup or delivery and a time — the order lands on the pre-orders board. Control shown items, delivery fee, minimum order and lead time.',
        'fits' => ['حلويات', 'مخابز', 'مطاعم', 'محلات', 'ورود وهدايا'],
        'requires' => ['orders'],
    ],
    'batches' => [
        'ar' => 'تتبع تاريخ الصلاحية ورقم الدفعة للأصناف المختارة: يُسجَّل عند استلام المشتريات، ويُصرف الأقرب انتهاءً أولاً تلقائياً في البيع والتحويل والإنتاج، مع لوحة لما انتهى وما سينتهي خلال أيام، وإتلاف التالف بقيده المحاسبي.',
        'en' => 'Expiry date and batch number for chosen items: recorded when purchases are received, earliest expiry sold first automatically (sales, transfers, production), with a board of expired / expiring stock and write-off with its ledger entry.',
        'fits' => ['حلويات ومخابز', 'سوبرماركت', 'صيدليات', 'مواد غذائية', 'مستحضرات تجميل'],
        'requires' => ['inventory'],
    ],
    'serials' => [
        'ar' => 'تتبّع كل وحدة برقمها التسلسلي وضمانها للأصناف المختارة (ألواح، إنفرترات، بطاريات، أجهزة): تسجيل عند الاستلام، تسليم للعميل مع احتساب تاريخ الضمان تلقائياً من مدة ضمان الصنف، لوحة ضمان (سارٍ/يقارب الانتهاء/منتهٍ)، بحث فوري بالرقم التسلسلي، وتتبّع الأعطال والاستبدال تحت الضمان.',
        'en' => 'Track every unit by serial number and warranty for chosen items (panels, inverters, batteries, devices): register on receipt, hand to a customer with the warranty end computed automatically from the item warranty period, a warranty board (active / expiring / expired), instant serial lookup, and faulty/replacement tracking.',
        'fits' => ['الطاقة الشمسية', 'إلكترونيات وأجهزة', 'مكيفات وتبريد', 'معدات ثقيلة', 'اتصالات'],
        'requires' => ['inventory'],
    ],
    'loyalty' => [
        'ar' => 'نقاط ولاء على كل عملية شراء تُستبدل خصماً عند الكاشير، ومحفظة رصيد مسبق للعميل (شحن، دفع من الرصيد، استرداد) مع كشف حركات لكل عميل.',
        'en' => 'Loyalty points on every purchase, redeemed as a discount at the till, plus a prepaid customer wallet (top-up, pay from balance, refund) with a per-customer statement.',
        'fits' => ['محلات', 'حلويات', 'مطاعم ومقاهي', 'مغاسل', 'صالونات'],
    ],
];
