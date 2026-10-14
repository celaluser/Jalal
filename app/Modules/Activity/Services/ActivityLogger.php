<?php

namespace App\Modules\Activity\Services;

use App\Modules\Activity\Models\ActivityLog;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/** Writes the audit trail. A logging problem never breaks the action being logged. */
class ActivityLogger
{
    /** Never written to the log, even as "changed". */
    private const HIDDEN = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'updated_at', 'created_at', 'api_key', 'secret'];

    public function record(string $event, ?Model $subject = null, array $changes = [], ?string $label = null, ?int $restaurantId = null): void
    {
        try {
            $user = auth()->user();
            $restaurantId ??= $subject?->restaurant_id ?? app(TenantContext::class)->id();

            ActivityLog::withoutGlobalScopes()->create([
                'restaurant_id' => $restaurantId, 'user_id' => $user?->id, 'user_name' => $user?->name ? mb_substr($user->name, 0, 120) : null,
                'event' => $event, 'subject_type' => $subject ? class_basename($subject) : null, 'subject_id' => $subject?->getKey(),
                'label' => $label ?? ($subject ? $this->labelOf($subject) : null), 'changes' => $changes ?: null, 'ip' => request()?->ip(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @return array<string, array{0: mixed, 1: mixed}> old and new value of every changed field */
    public function diff(Model $model): array
    {
        $out = [];

        foreach ($model->getChanges() as $key => $new) {
            if (in_array($key, self::HIDDEN, true) || str_contains($key, 'secret') || str_contains($key, 'token')) {
                continue;
            }

            $out[$key] = [$this->short($model->getOriginal($key)), $this->short($new)];
        }

        return $out;
    }

    public function labelOf(Model $m): string
    {
        $name = $m->name ?? $m->title ?? $m->code ?? $m->email ?? null;

        if (is_array($name)) {
            $name = reset($name) ?: null;
        }

        return mb_substr((string) ($name ?? '#'.$m->getKey()), 0, 190);
    }

    private function short(mixed $value): mixed
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return is_string($value) ? mb_substr($value, 0, 160) : $value;
    }
}
