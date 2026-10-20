<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tenant = app('currentTenant');
        $enabled = array_flip($tenant->enabledModuleKeys());
        $period = $request->query('period') === 'year' ? now()->startOfYear() : now()->startOfMonth();

        $out = [
            'tenant' => [
                'name' => $tenant->name,
                'sector' => $tenant->sectorPreset?->name_ar,
                'plan' => $tenant->plan?->name_ar,
                'currency' => $tenant->currency,
                'subscription_status' => $tenant->subscription_status,
                'renews_at' => $tenant->subscription_renews_at?->toDateString(),
                'trial_ends_at' => $tenant->trial_ends_at?->toDateString(),
            ],
            'counts' => [
                'users' => User::count(),
                'roles' => Role::count(),
                'modules' => count($enabled),
            ],
        ];

        // Sales KPIs only when the POS engine is on for this tenant.
        if (isset($enabled['pos']) || isset($enabled['inventory'])) {
            $sales = Sale::where('status', 'completed')->where('date', '>=', $period->toDateString());
            $today = Sale::where('status', 'completed')->whereDate('date', now()->toDateString());
            $out['sales'] = [
                'period_total' => (float) (clone $sales)->sum('total'),
                'period_invoices' => (clone $sales)->count(),
                'today_total' => (float) (clone $today)->sum('total'),
                'today_invoices' => (clone $today)->count(),
                'unpaid' => (float) Sale::where('status', 'completed')->selectRaw('coalesce(sum(total-paid),0) as d')->value('d'),
                'by_day' => (clone $sales)->selectRaw('date, sum(total) as total')->groupBy('date')->orderBy('date')->get(),
            ];
            $out['low_stock'] = Item::where('track_stock', true)->where('is_active', true)
                ->withSum('stockLevels as stock', 'qty')->get()
                ->filter(fn ($i) => (float) ($i->stock ?? 0) <= (float) $i->min_stock)
                ->count();
        }

        return response()->json($out);
    }
}
