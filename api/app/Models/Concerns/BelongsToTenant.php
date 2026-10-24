<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Multi-tenant scoping. Any model using this trait is automatically:
 *   - filtered to the current tenant on every query, and
 *   - stamped with the current tenant_id on create.
 *
 * The current tenant is bound into the container as `currentTenantId` by the
 * SetTenantContext middleware. When nothing is bound (console, public routes)
 * or it is null (platform super-admin), the scope is disabled.
 */
trait BelongsToTenant
{
    // Empty form fields never crash a NOT NULL column (see the trait).
    use RespectsColumnDefaults;

    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (self::currentTenantId() !== null) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', self::currentTenantId());
            }
        });

        static::creating(function (Model $model) {
            if ($model->getAttribute('tenant_id') === null && self::currentTenantId() !== null) {
                $model->setAttribute('tenant_id', self::currentTenantId());
            }
        });
    }

    public static function currentTenantId(): ?int
    {
        return app()->bound('currentTenantId') ? app('currentTenantId') : null;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
