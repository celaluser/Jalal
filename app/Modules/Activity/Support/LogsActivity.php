<?php

namespace App\Modules\Activity\Support;

use App\Modules\Activity\Services\ActivityLogger;

/** Add to a model to record who created, changed or deleted it. */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn ($m) => app(ActivityLogger::class)->record('created', $m));
        static::updated(function ($m) {
            $diff = app(ActivityLogger::class)->diff($m);

            if ($diff !== []) {
                app(ActivityLogger::class)->record('updated', $m, $diff);
            }
        });
        static::deleted(fn ($m) => app(ActivityLogger::class)->record('deleted', $m));
    }
}
