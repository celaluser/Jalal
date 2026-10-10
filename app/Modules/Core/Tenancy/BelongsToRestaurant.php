<?php

namespace App\Modules\Core\Tenancy;

use App\Modules\Tenancy\Models\Restaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Add to every tenant-owned model. Applies the TenantScope and stamps restaurant_id on create.
 */
trait BelongsToRestaurant
{
    public static function bootBelongsToRestaurant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            if ($model->restaurant_id !== null) {
                return;
            }

            $id = app(TenantContext::class)->id();

            if ($id === null) {
                throw new LogicException(sprintf(
                    'Cannot create %s without a restaurant: no tenant in context and no restaurant_id given.',
                    $model::class
                ));
            }

            $model->restaurant_id = $id;
        });
    }

    /**
     * Deliberately look across all tenants. Only platform (super admin) code may call this;
     * it is the one greppable escape hatch next to TenantContext::bypass().
     */
    public function scopeAllTenants(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
