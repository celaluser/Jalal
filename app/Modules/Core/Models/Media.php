<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Uploaded file. restaurant_id is null for platform-level files (site logo, landing page images).
 * A tenant sees its own files plus platform files; with no tenant only platform files are visible.
 */
class Media extends Model
{
    protected $table = 'media';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $context = app(TenantContext::class);

            if ($context->isBypassed()) {
                return;
            }

            $column = $builder->getModel()->qualifyColumn('restaurant_id');

            $context->has()
                ? $builder->where(fn (Builder $q) => $q->where($column, $context->id())->orWhereNull($column))
                : $builder->whereNull($column);
        });
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
