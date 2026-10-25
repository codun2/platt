<?php

namespace App\Http\Controllers\Api\Super;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Plan;
use App\Models\SectorPreset;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\User;
use App\Services\TenantRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Tenant::with('sectorPreset:id,key,name_ar,icon', 'plan:id,key,name_ar')
            ->withCount('users')
            ->orderByDesc('id');

        if ($s = $request->query('q')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")->orWhere('slug', 'like', "%$s%"));
        }
        if ($st = $request->query('status')) {
            $q->where('subscription_status', $st);
        }

        return response()->json($q->paginate(25));
    }

    public function show(int $id): JsonResponse
    {
        $tenant = Tenant::with('sectorPreset:id,key,name_ar', 'plan', 'subscriptions.plan:id,name_ar')
            ->withCount('users')->findOrFail($id);

        $enabled = TenantModule::where('tenant_id', $id)->where('enabled', true)->pluck('module_id')->flip();
        $modules = Module::whereNull('parent_key')->where('is_active', true)->orderBy('sort')->get()
            ->map(fn (Module $m) => [
                'id' => $m->id, 'key' => $m->key, 'name_ar' => $m->name_ar, 'name_en' => $m->name_en, 'icon' => $m->icon,
                'engine' => $m->engine, 'is_core' => $m->is_core, 'enabled' => $m->is_core || isset($enabled[$m->id]),
                // Advanced add-on: what it does (shown so the admin knows what is being switched on).
                'addon' => $m->engine === 'addons' ? config('addons.'.$m->key) : null,
            ]);

        $owner = User::withoutGlobalScopes()->where('tenant_id', $id)->where('is_owner', true)->first(['id', 'name', 'email', 'phone', 'last_login_at']);

        return response()->json(['tenant' => $tenant, 'modules' => $modules, 'owner' => $owner]);
    }

    /** Super-admin creates a tenant on behalf of a customer. */
    public function store(Request $request, TenantRegistrar $registrar): JsonResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'sector' => ['required', 'string', 'exists:sector_presets,key'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $result = $registrar->register($data);

        return response()->json($result['tenant'], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'sector_preset_id' => ['nullable', 'exists:sector_presets,id'],
            'status' => ['sometimes', Rule::in(['active', 'suspended'])],
            'subscription_status' => ['sometimes', Rule::in(['trial', 'active', 'expired', 'suspended'])],
            'subscription_renews_at' => ['nullable', 'date'],
            'trial_ends_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $tenant->fill($data)->save();

        return response()->json($tenant->fresh()->load('plan', 'sectorPreset'));
    }

    /** Enable / disable one module for a tenant. */
    public function toggleModule(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $data = $request->validate([
            'module_id' => ['required', 'exists:modules,id'],
            'enabled' => ['required', 'boolean'],
        ]);

        TenantModule::updateOrCreate(
            ['tenant_id' => $tenant->id, 'module_id' => $data['module_id']],
            ['enabled' => $data['enabled']],
        );
        // An add-on that builds on another (e.g. online page → pre-orders) switches it on too.
        $key = Module::whereKey($data['module_id'])->value('key');
        if ($data['enabled'] && ($needs = config("addons.$key.requires"))) {
            foreach (Module::whereIn('key', $needs)->pluck('id') as $mid) {
                TenantModule::updateOrCreate(['tenant_id' => $tenant->id, 'module_id' => $mid], ['enabled' => true]);
            }
        }

        return response()->json(['ok' => true, 'enabled_keys' => $tenant->enabledModuleKeys()]);
    }

    /** Re-apply a sector preset (adds the preset's modules; never removes). */
    public function applyPreset(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $data = $request->validate(['preset_key' => ['required', 'exists:sector_presets,key']]);
        $tenant->applyPreset(SectorPreset::where('key', $data['preset_key'])->firstOrFail());

        return response()->json(['ok' => true, 'enabled_keys' => $tenant->enabledModuleKeys()]);
    }

    /** Record a paid period and push the renewal date forward. */
    public function renew(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $data = $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:24'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $plan = isset($data['plan_id']) ? Plan::find($data['plan_id']) : $tenant->plan;
        $start = $tenant->subscription_renews_at && $tenant->subscription_renews_at->isFuture()
            ? $tenant->subscription_renews_at->copy()
            : now()->startOfDay();
        $end = $start->copy()->addMonths($data['months']);

        $sub = Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan?->id,
            'period_start' => $start,
            'period_end' => $end,
            'amount' => $data['amount'] ?? ($plan ? $plan->price_monthly * $data['months'] : 0),
            'status' => 'paid',
            'note' => $data['note'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $tenant->forceFill([
            'plan_id' => $plan?->id,
            'subscription_status' => 'active',
            'status' => 'active',
            'subscription_renews_at' => $end,
        ])->save();

        return response()->json(['subscription' => $sub, 'tenant' => $tenant->fresh()->load('plan')]);
    }

    /** Super-admin can reset the owner's password. */
    public function resetOwnerPassword(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:6']]);
        $owner = User::withoutGlobalScopes()->where('tenant_id', $id)->where('is_owner', true)->firstOrFail();
        $owner->forceFill(['password' => $data['password']])->save();

        return response()->json(['ok' => true]);
    }
}
