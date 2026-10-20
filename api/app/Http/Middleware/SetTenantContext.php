<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the authenticated user's tenant into the container so BelongsToTenant
 * scopes every query. Super-admins (tenant_id = null) stay unscoped.
 * Also blocks suspended / expired tenants.
 */
class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            return response()->json(['message' => 'هذا الحساب معطّل.'], 403);
        }

        if ($user && $user->tenant_id) {
            $tenant = $user->tenant;

            if (! $tenant || ! $tenant->isActive()) {
                return response()->json(['message' => 'اشتراك الشركة موقوف أو منتهٍ. تواصل مع الدعم.'], 403);
            }

            app()->instance('currentTenantId', $tenant->id);
            app()->instance('currentTenant', $tenant);
        }

        return $next($request);
    }
}
