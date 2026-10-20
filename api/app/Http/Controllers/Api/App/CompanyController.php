<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Tenant's own company settings + read-only view of its enabled modules. */
class CompanyController extends Controller
{
    public function show(): JsonResponse
    {
        $tenant = app('currentTenant')->load('sectorPreset:id,key,name_ar', 'plan:id,key,name_ar,price_monthly,max_users');
        $enabled = array_flip($tenant->enabledModuleKeys());

        $modules = Module::whereNull('parent_key')->where('is_active', true)->orderBy('sort')->get()
            ->map(fn (Module $m) => [
                'key' => $m->key, 'name_ar' => $m->name_ar, 'icon' => $m->icon, 'engine' => $m->engine,
                'is_core' => $m->is_core, 'enabled' => isset($enabled[$m->key]),
            ]);

        return response()->json(['tenant' => $tenant, 'modules' => $modules]);
    }

    public function update(Request $request): JsonResponse
    {
        $tenant = app('currentTenant');
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email'],
            'currency' => ['nullable', 'string', 'max:8'],
        ]);
        $tenant->fill($data)->save();

        return response()->json($tenant->fresh());
    }
}
