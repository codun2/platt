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
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // Printed on invoices / quotes (stored in tenants.settings JSON).
            'settings' => ['nullable', 'array'],
            'settings.address' => ['nullable', 'string', 'max:255'],
            'settings.tax_number' => ['nullable', 'string', 'max:50'],
            'settings.cr_number' => ['nullable', 'string', 'max:50'],
            'settings.bank_details' => ['nullable', 'string', 'max:500'],
            'settings.invoice_terms' => ['nullable', 'string', 'max:1000'],
            'settings.invoice_footer' => ['nullable', 'string', 'max:255'],
        ]);
        if (array_key_exists('settings', $data)) {
            $data['settings'] = array_merge((array) ($tenant->settings ?? []), (array) ($data['settings'] ?? []));
        }
        $tenant->fill($data)->save();

        return response()->json($tenant->fresh());
    }
}
