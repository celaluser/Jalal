<?php

namespace App\Modules\Billing\Support;

use App\Modules\Tenancy\Models\Restaurant;
use Closure;

/**
 * How many of each limited resource a restaurant currently uses. Feature modules register a counter
 * for the limit keys they own (see Plan::LIMITS), e.g. the menu module registers "products":
 *
 *   UsageRegistry::register('products', fn (Restaurant $r) => Product::count());
 *
 * Keys without a counter show their limit only, with no usage bar.
 */
final class UsageRegistry
{
    /** @var array<string, Closure(Restaurant): int> */
    private static array $counters = [];

    /** @param Closure(Restaurant): int $counter */
    public static function register(string $key, Closure $counter): void
    {
        self::$counters[$key] = $counter;
    }

    public static function count(string $key, Restaurant $restaurant): ?int
    {
        return isset(self::$counters[$key]) ? (int) (self::$counters[$key])($restaurant) : null;
    }

    public static function flush(): void
    {
        self::$counters = [];
    }
}
