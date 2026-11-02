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
        // ---- page customization (landing builder) ----
        'accent' => null,           // tenant-chosen theme colour (falls back to sector accent)
        'theme' => 'light',         // light | dark
        'layout' => 'classic',      // classic | modern | minimal
        'logo' => null,             // uploaded logo path on the public disk
        'tagline' => null,          // short slogan under the name in the hero
        'about' => null,            // longer «about us» paragraph
        'socials' => [],            // { instagram, tiktok, snapchat, x, facebook, website, email, maps }
        'sections' => self::SECTIONS, // ordered content blocks with on/off
    ];

    /** Orderable content blocks shown after the hero (hero is always first). */
    public const SECTIONS = [
        ['key' => 'products', 'on' => true],
        ['key' => 'about', 'on' => false],
        ['key' => 'contact', 'on' => true],
    ];

    public const SECTION_KEYS = ['products', 'about', 'contact'];
    public const SOCIAL_KEYS = ['instagram', 'tiktok', 'snapchat', 'x', 'facebook', 'website', 'email', 'maps'];

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
        $s['sections'] = self::normalizeSections($s['sections'] ?? []);
        $s['socials'] = array_intersect_key((array) ($s['socials'] ?? []), array_flip(self::SOCIAL_KEYS));
        $s['theme'] = in_array($s['theme'] ?? null, ['light', 'dark'], true) ? $s['theme'] : 'light';
        $s['layout'] = in_array($s['layout'] ?? null, ['classic', 'modern', 'minimal'], true) ? $s['layout'] : 'classic';

        return $s;
    }

    /** Keep the stored order, drop unknown keys, and append any missing block. */
    public static function normalizeSections(array $stored): array
    {
        $out = [];
        $seen = [];
        foreach ($stored as $row) {
            $k = is_array($row) ? ($row['key'] ?? null) : null;
            if ($k && in_array($k, self::SECTION_KEYS, true) && ! isset($seen[$k])) {
                $out[] = ['key' => $k, 'on' => (bool) ($row['on'] ?? true)];
                $seen[$k] = true;
            }
        }
        foreach (self::SECTIONS as $def) {
            if (! isset($seen[$def['key']])) {
                $out[] = $def;
            }
        }

        return $out;
    }

    /** Public URL of the tenant's uploaded logo, if any. */
    public static function logoUrl(array $s): ?string
    {
        return ! empty($s['logo']) ? url('storage/'.$s['logo']) : null;
    }

    /** Tenant by its store link (custom handle first, then the slug). */
    public static function findByHandle(string $handle): ?Tenant
    {
        $handle = mb_strtolower(trim($handle));

        return Tenant::where('settings->storefront->handle', $handle)->first() ?? Tenant::where('slug', $handle)->first();
    }
}
