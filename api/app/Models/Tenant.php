<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name', 'slug', 'sector_preset_id', 'plan_id', 'phone', 'email', 'country', 'currency', 'tax_rate', 'settings',
        'logo', 'status', 'subscription_status', 'trial_ends_at', 'subscription_renews_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'date:Y-m-d',
            'subscription_renews_at' => 'date:Y-m-d',
            'tax_rate' => 'decimal:2',
            'settings' => 'array',
        ];
    }

    public function sectorPreset(): BelongsTo
    {
        return $this->belongsTo(SectorPreset::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->orderByDesc('period_start');
    }

    public function tenantModules(): HasMany
    {
        return $this->hasMany(TenantModule::class);
    }

    /** Modules enabled for this tenant (pivot `enabled` = true) plus core modules. */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'tenant_modules')->withPivot('enabled')->withTimestamps();
    }

    /** Keys of modules the tenant may use: core modules + explicitly enabled ones. */
    public function enabledModuleKeys(): array
    {
        $enabled = $this->modules()->wherePivot('enabled', true)->where('modules.is_active', true)->pluck('modules.key');
        $core = Module::where('is_core', true)->where('is_active', true)->pluck('key');

        return $enabled->merge($core)->unique()->values()->all();
    }

    /** Copy the preset's modules into tenant_modules (idempotent). */
    public function applyPreset(SectorPreset $preset): void
    {
        $this->sector_preset_id = $preset->id;
        $this->save();

        foreach ($preset->modules()->pluck('modules.id') as $moduleId) {
            TenantModule::firstOrCreate(
                ['tenant_id' => $this->id, 'module_id' => $moduleId],
                ['enabled' => true],
            );
        }
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && in_array($this->subscription_status, ['trial', 'active'], true);
    }
}
