<?php

namespace App\Modules\Ai\Services;

use App\Models\User;
use App\Modules\Ai\Exceptions\AiException;
use App\Modules\Ai\Models\AiUsage;
use App\Modules\Billing\Services\LimitGuard;
use App\Modules\Core\Services\SettingsService;
use App\Modules\Tenancy\Models\Restaurant;

/**
 * Monthly AI allowance per restaurant. The plan's "ai_credits" limit is the allowance (empty =
 * unlimited, 0 = none) and the admin sets how many credits each kind of task costs. Credits are only
 * charged for calls that succeeded.
 */
class AiCredits
{
    public const TASKS = ['menu_import' => 10, 'description' => 1, 'translation' => 2, 'allergens' => 1, 'review_reply' => 1, 'insights' => 5];

    public function __construct(private readonly LimitGuard $limits, private readonly SettingsService $settings) {}

    public function cost(string $task): int
    {
        $configured = $this->settings->get("ai.cost.{$task}");

        return $configured === null || $configured === '' ? (self::TASKS[$task] ?? 1) : max(0, (int) $configured);
    }

    /** @return int|null null = unlimited */
    public function allowance(Restaurant $restaurant): ?int
    {
        return $this->limits->limit($restaurant, 'ai_credits');
    }

    /** Credits used since the start of the current month. */
    public function used(): int
    {
        return (int) AiUsage::where('created_at', '>=', now()->startOfMonth())->sum('credits');
    }

    /** @return int|null null = unlimited */
    public function remaining(Restaurant $restaurant): ?int
    {
        $allowance = $this->allowance($restaurant);

        return $allowance === null ? null : max(0, $allowance - $this->used());
    }

    /** @throws AiException */
    public function ensure(Restaurant $restaurant, string $task): void
    {
        $remaining = $this->remaining($restaurant);

        if ($remaining !== null && $remaining < $this->cost($task)) {
            throw new AiException('no_credits');
        }
    }

    public function charge(string $task, string $provider, int $in, int $out, ?User $by = null): void
    {
        AiUsage::create(['user_id' => $by?->id, 'task' => $task, 'provider' => $provider, 'credits' => $this->cost($task), 'input_tokens' => $in, 'output_tokens' => $out]);
    }

    /** @return array{used: int, allowance: int|null, remaining: int|null} */
    public function summary(Restaurant $restaurant): array
    {
        return ['used' => $this->used(), 'allowance' => $this->allowance($restaurant), 'remaining' => $this->remaining($restaurant)];
    }
}
