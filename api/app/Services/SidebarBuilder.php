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

        $visible = $modules->filter(function (Module $m) use ($enabledKeys, $permissions, $allPerms) {
            $root = $m->parent_key ?: $m->key;

            return isset($enabledKeys[$m->key]) || isset($enabledKeys[$root])
                ? ($allPerms || isset($permissions[$m->viewPermissionKey()]))
                : false;
        });

        return $this->tree($visible);
    }

    private function tree(Collection $modules): array
    {
        $byParent = $modules->groupBy(fn (Module $m) => $m->parent_key ?? '');

        $build = function (string $parent) use (&$build, $byParent): array {
            return ($byParent->get($parent) ?? collect())->map(fn (Module $m) => [
                'key' => $m->key,
                'name_ar' => $m->name_ar,
                'name_en' => $m->name_en,
                'icon' => $m->icon,
                'route' => $m->route,
                'engine' => $m->engine,
                'children' => $build($m->key),
            ])->values()->all();
        };

        return $build('');
    }
}
