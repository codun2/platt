<?php

namespace App\Http\Middleware;

use App\Models\Module;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('perm:users.manage')
 *
 * Two checks: (1) the user holds the permission (owners hold everything), and
 * (2) when the permission belongs to a module (`<module_key>.view|manage`),
 * that module must be enabled for the tenant — so an owner of a retail tenant
 * still cannot call the bookings API.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasPermission($permission)) {
            return response()->json(['message' => 'ليس لديك صلاحية لهذا الإجراء.'], 403);
        }

        if ($user->tenant_id && ! $this->moduleEnabled($permission)) {
            return response()->json(['message' => 'هذه الوحدة غير مفعّلة لحسابكم.'], 403);
        }

        return $next($request);
    }

    private function moduleEnabled(string $permission): bool
    {
        if (! preg_match('/^([a-z0-9_]+)\.(view|manage)$/', $permission, $m)) {
            return true;
        }
        $module = Module::where('key', $m[1])->first(['key', 'parent_key', 'is_core']);
        if (! $module || $module->is_core) {
            return true;    // not a module permission (users.manage…) or always-on core
        }
        $enabled = array_flip(app('currentTenant')->enabledModuleKeys());
        $root = $module->parent_key ?: $module->key;

        return isset($enabled[$module->key]) || isset($enabled[$root]);
    }
}
