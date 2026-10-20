<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Tenant-scoped routes: the user must belong to a tenant (not a platform super-admin). */
class EnsureTenantUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->tenant_id) {
            return response()->json(['message' => 'هذا المسار خاص بمستخدمي الشركات.'], 403);
        }

        return $next($request);
    }
}
