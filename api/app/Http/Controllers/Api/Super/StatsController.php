<?php

namespace App\Http\Controllers\Api\Super;

use App\Http\Controllers\Controller;
use App\Models\SectorPreset;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $byStatus = Tenant::selectRaw('subscription_status, count(*) as c')->groupBy('subscription_status')->pluck('c', 'subscription_status');

        return response()->json([
            'tenants' => Tenant::count(),
            'users' => User::withoutGlobalScopes()->where('is_super_admin', false)->count(),
            'by_status' => [
                'trial' => (int) ($byStatus['trial'] ?? 0),
                'active' => (int) ($byStatus['active'] ?? 0),
                'expired' => (int) ($byStatus['expired'] ?? 0),
                'suspended' => (int) ($byStatus['suspended'] ?? 0),
            ],
            'due_this_week' => Tenant::whereIn('subscription_status', ['trial', 'active'])
                ->whereBetween('subscription_renews_at', [now()->startOfDay(), now()->addDays(7)])->count(),
            'revenue_this_month' => (float) Subscription::where('status', 'paid')
                ->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('amount'),
            'by_sector' => SectorPreset::withCount('tenants')->orderByDesc('tenants_count')->get(['id', 'key', 'name_ar', 'icon'])
                ->map(fn ($p) => ['key' => $p->key, 'name_ar' => $p->name_ar, 'icon' => $p->icon, 'count' => $p->tenants_count]),
            'recent' => Tenant::with('sectorPreset:id,name_ar')->orderByDesc('id')->limit(8)->get(['id', 'name', 'sector_preset_id', 'subscription_status', 'created_at']),
        ]);
    }
}
