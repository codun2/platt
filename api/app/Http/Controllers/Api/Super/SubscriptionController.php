<?php

namespace App\Http\Controllers\Api\Super;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /** Subscription overview: every tenant with plan, renewal date and status. */
    public function index(Request $request): JsonResponse
    {
        $q = Tenant::with('plan:id,key,name_ar,price_monthly', 'sectorPreset:id,name_ar')
            ->orderBy('subscription_renews_at');

        if ($st = $request->query('status')) {
            $q->where('subscription_status', $st);
        }
        if ($request->boolean('due_soon')) {
            $q->whereDate('subscription_renews_at', '<=', now()->addDays(7));
        }

        return response()->json($q->get(['id', 'name', 'slug', 'plan_id', 'sector_preset_id', 'status', 'subscription_status', 'trial_ends_at', 'subscription_renews_at']));
    }

    /** Payment / period history. */
    public function history(Request $request): JsonResponse
    {
        $q = Subscription::with('tenant:id,name', 'plan:id,name_ar')->orderByDesc('id');
        if ($t = $request->query('tenant_id')) {
            $q->where('tenant_id', $t);
        }

        return response()->json($q->paginate(50));
    }

    /** Mark tenants whose renewal date has passed as expired (also run by scheduler). */
    public function expireOverdue(): JsonResponse
    {
        $n = Tenant::whereIn('subscription_status', ['trial', 'active'])
            ->whereNotNull('subscription_renews_at')
            ->whereDate('subscription_renews_at', '<', now())
            ->update(['subscription_status' => 'expired']);

        return response()->json(['expired' => $n]);
    }
}
