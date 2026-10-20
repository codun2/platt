<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tenant = app('currentTenant');

        return response()->json([
            'tenant' => [
                'name' => $tenant->name,
                'sector' => $tenant->sectorPreset?->name_ar,
                'plan' => $tenant->plan?->name_ar,
                'subscription_status' => $tenant->subscription_status,
                'renews_at' => $tenant->subscription_renews_at?->toDateString(),
                'trial_ends_at' => $tenant->trial_ends_at?->toDateString(),
            ],
            'counts' => [
                'users' => User::count(),
                'roles' => Role::count(),
                'modules' => count($tenant->enabledModuleKeys()),
            ],
        ]);
    }
}
