<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds the dynamic sidebar: a tree of modules filtered by
 *   (module enabled for the tenant) AND (user has `<key>.view`).
 * Nothing is hard-coded — the menu is driven entirely by the `modules` table.
 */
class SidebarBuilder
{
    public function forUser(User $user, Tenant $tenant): array
    {
        $enabledKeys = array_flip($tenant->enabledModuleKeys());
        $permissions = array_flip($user->permissionKeys());
        $allPerms = $user->is_owner || $user->is_super_admin;

        $modules = Module::where('is_active', true)->orderBy('sort')->get();

        $identity = SectorIdentity::forTenant($tenant);
        $hidden = array_flip($identity['hide']);
        // "بيانات الشركة" follows what the business is (بيانات المحل، بيانات المزرعة…).
        $identity['labels']['settings_company'] ??= ['بيانات '.$identity['org'][0], $identity['org'][1].' profile'];

        $visible = $modules->filter(function (Module $m) use ($enabledKeys, $permissions, $allPerms, $hidden) {
            $root = $m->parent_key ?: $m->key;
            if (isset($hidden[$m->key]) || isset($hidden[$root])) {
                return false;
            }

            return isset($enabledKeys[$m->key]) || isset($enabledKeys[$root])
                ? ($allPerms || isset($permissions[$m->viewPermissionKey()]))
                : false;
        });

        return $this->ordered($this->tree($visible, $identity['labels']), $identity['order']);
    }

    private function tree(Collection $modules, array $labels): array
    {
        $byParent = $modules->groupBy(fn (Module $m) => $m->parent_key ?? '');

        $build = function (string $parent) use (&$build, $byParent, $labels): array {
            return ($byParent->get($parent) ?? collect())->map(function (Module $m) use (&$build, $labels) {
                $l = $labels[$m->key] ?? null;

                return [
                    'key' => $m->key,
                    'name_ar' => $l[0] ?? $m->name_ar,
                    'name_en' => $l[1] ?? $m->name_en,
                    'icon' => $l[2] ?? $m->icon,
                    'route' => $m->route,
                    'engine' => $m->engine,
                    'children' => $build($m->key),
                ];
            })->values()->all();
        };

        return $build('');
    }

    /**
     * Sector order for top-level items: dashboard first, then the sector's
     * listed modules (any other enabled module goes just before the shared
     * back office), and settings last.
     */
    private function ordered(array $items, array $order): array
    {
        $rank = array_flip($order);
        // Extra enabled modules not in the sector's list are still "work"
        // modules: slot them just before the shared back office.
        $backOffice = ['crm', 'accounting', 'hr', 'whatsapp'];
        $firstBack = count($order);
        foreach ($order as $n => $k) {
            if (in_array($k, $backOffice, true)) { $firstBack = $n; break; }
        }
        $pos = fn (array $i, int $idx) => match (true) {
            $i['key'] === 'dashboard' => -1,
            $i['key'] === 'settings' => PHP_INT_MAX,
            isset($rank[$i['key']]) => $rank[$i['key']] * 1000,
            default => $firstBack * 1000 - 500 + $idx,
        };

        $keyed = [];
        foreach ($items as $idx => $i) {
            $keyed[] = [$pos($i, $idx), $idx, $i];
        }
        usort($keyed, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_column($keyed, 2);
    }
}
