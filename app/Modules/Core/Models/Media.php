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

    /** Longest side of the small copy kept for lists and cards. */
    public const THUMB_SIZE = 480;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['has_thumb' => 'boolean'];
    }

    public static function thumbPath(string $path): string
    {
        return preg_replace('/\.webp$/', '_thumb.webp', $path);
    }

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

    /** The small copy when there is one (older files only have the full image). */
    public function thumbUrl(): string
    {
        return $this->has_thumb ? Storage::disk($this->disk)->url(self::thumbPath($this->path)) : $this->url();
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
