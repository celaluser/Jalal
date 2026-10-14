<?php

namespace App\Modules\Activity\Models;

use App\Modules\Core\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;

/** One line of "who changed what, when". Written by ActivityLogger, never edited. */
class ActivityLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    /** Read only inside a restaurant: the log of one never shows in another. Written through ActivityLogger. */
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }
}
