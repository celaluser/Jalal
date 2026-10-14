<?php

namespace App\Modules\Orders\Support;

/**
 * Life of an order. One linear path with a way out (cancel) until it is done:
 *
 *   new -> accepted -> preparing -> ready -> completed        (any open step can go to cancelled)
 *
 * "completed" means served at the table, picked up or handed to the courier, depending on the type.
 */
final class OrderStatus
{
    public const NEW = 'new';

    public const ACCEPTED = 'accepted';

    public const PREPARING = 'preparing';

    public const READY = 'ready';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    /** @var list<string> in order */
    public const FLOW = [self::NEW, self::ACCEPTED, self::PREPARING, self::READY, self::COMPLETED];

    /** Statuses where work is still to be done. */
    public const OPEN = [self::NEW, self::ACCEPTED, self::PREPARING, self::READY];

    public const FINAL = [self::COMPLETED, self::CANCELLED];

    /** Steps the kitchen may take with only the kitchen permission. */
    public const KITCHEN_STEPS = [self::PREPARING, self::READY];

    /** @return list<string> statuses an order in $from can move to */
    public static function next(string $from): array
    {
        return match ($from) {
            self::NEW => [self::ACCEPTED, self::PREPARING, self::CANCELLED],
            self::ACCEPTED => [self::PREPARING, self::CANCELLED],
            self::PREPARING => [self::READY, self::CANCELLED],
            self::READY => [self::COMPLETED, self::CANCELLED],
            default => [],
        };
    }

    public static function canMove(string $from, string $to): bool
    {
        return in_array($to, self::next($from), true);
    }

    public static function isOpen(string $status): bool
    {
        return in_array($status, self::OPEN, true);
    }

    /** The single forward step shown as the main button on a card. */
    public static function forward(string $from): ?string
    {
        return match ($from) {
            self::NEW => self::ACCEPTED,
            self::ACCEPTED => self::PREPARING,
            self::PREPARING => self::READY,
            self::READY => self::COMPLETED,
            default => null,
        };
    }
}
