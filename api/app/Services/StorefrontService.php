<?php

namespace App\Services;

use App\Models\Tenant;

/** Add-on «صفحة الطلب أونلاين»: per-tenant settings (tenants.settings.storefront) and the public link. */
class StorefrontService
{
    public const DEFAULTS = [
        'open' => false,            // the tenant switches the page on when ready
        'handle' => null,           // link name: /app/store/<handle> (default: tenant slug)
        'title' => null,            // shown on top (default: tenant name)
        'welcome' => null,          // short line under the title
        'whatsapp' => null,         // shop number for «تواصل معنا» / order confirmation
        'pickup' => true,
        'delivery' => true,
        'delivery_fee' => 0,
        'min_order' => 0,
        'lead_hours' => 2,          // earliest due time = now + lead time
        'hide_out_of_stock' => true,
        'notice' => null,           // e.g. «الطلبات قبل يوم للكيك»
    ];

    public static function enabled(?Tenant $tenant): bool
    {
        return $tenant && in_array('storefront', $tenant->enabledModuleKeys(), true);
    }

    public static function settings(Tenant $tenant): array
    {
        $own = (array) (($tenant->settings ?? [])['storefront'] ?? []);
        $s = array_merge(self::DEFAULTS, array_intersect_key($own, self::DEFAULTS));
        $s['handle'] = $s['handle'] ?: $tenant->slug;
        $s['title'] = $s['title'] ?: $tenant->name;

        return $s;
    }

    /** Tenant by its store link (custom handle first, then the slug). */
    public static function findByHandle(string $handle): ?Tenant
    {
        $handle = mb_strtolower(trim($handle));

        return Tenant::where('settings->storefront->handle', $handle)->first() ?? Tenant::where('slug', $handle)->first();
    }
}
