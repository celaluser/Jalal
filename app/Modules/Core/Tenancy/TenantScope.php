<?php

namespace App\Modules\Core\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts every query to the current tenant.
 *
 * Fails closed: with no tenant in context (and no explicit bypass) the query matches
 * nothing, so a forgotten context can never leak another restaurant's rows.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->isBypassed()) {
            return;
        }

        $column = $model->qualifyColumn('restaurant_id');

        if ($context->has()) {
            $builder->where($column, $context->id());

            return;
        }

        $builder->whereRaw('1 = 0');
    }
}
