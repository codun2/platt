<?php

namespace App\Services;

use App\Models\Tenant;

/**
 * Resolves a tenant's sector identity (accent, menu order, contextual labels,
 * hidden items) from config/sectors.php, falling back to the default entry.
 */
class SectorIdentity
{
    /** @return array{key:string,accent:string,order:array,labels:array,hide:array} */
    public static function forTenant(?Tenant $tenant): array
    {
        $key = $tenant?->sectorPreset?->key ?? 'default';

        return self::forKey($key);
    }

    public static function forKey(string $key): array
    {
        $all = config('sectors', []);
        $base = $all['default'] ?? [];
        $own = $all[$key] ?? [];

        return [
            'key' => isset($all[$key]) ? $key : 'default',
            'accent' => $own['accent'] ?? $base['accent'] ?? '#e11d48',
            'order' => $own['order'] ?? $base['order'] ?? [],
            'labels' => $own['labels'] ?? [],
            'hide' => $own['hide'] ?? [],
            'org' => $own['org'] ?? $base['org'] ?? ['الشركة', 'Company'],
        ];
    }

    /** Public, presentation-only part sent to the SPA. */
    public static function publicFor(?Tenant $tenant): array
    {
        $id = self::forTenant($tenant);

        return ['key' => $id['key'], 'accent' => $id['accent'], 'org' => $id['org']];
    }
}
