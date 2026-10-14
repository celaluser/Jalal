<?php

namespace App\Modules\Support\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    public const LEVELS = ['info', 'success', 'warning'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_dismissible' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** Active, started and not yet ended. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
