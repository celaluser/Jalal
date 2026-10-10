<?php

namespace App\Modules\Menu\Support;

use Carbon\CarbonInterface;

/**
 * "Only on weekdays from 12:00 to 15:00." A schedule is {days: [1..7 (Monday = 1)], from: "HH:MM", to: "HH:MM"};
 * no days means every day, no times mean all day, and a window that ends before it starts runs past midnight.
 * Null means no restriction at all.
 */
final class Schedule
{
    /** Turns submitted form values into a stored schedule, or null when nothing was restricted. @param array<string, mixed>|null $input */
    public static function fromInput(?array $input): ?array
    {
        if (! $input) {
            return null;
        }

        $days = array_values(array_unique(array_filter(array_map('intval', (array) ($input['days'] ?? [])), fn ($d) => $d >= 1 && $d <= 7)));
        sort($days);
        $from = self::time($input['from'] ?? null);
        $to = self::time($input['to'] ?? null);

        // A window needs both ends; half a window is ignored rather than guessed.
        if ($from === null || $to === null) {
            $from = $to = null;
        }

        if (count($days) === 7) {
            $days = [];
        }

        return $days === [] && $from === null ? null : ['days' => $days, 'from' => $from, 'to' => $to];
    }

    /** Is the schedule open at this moment? The moment must already be in the restaurant's time zone. @param array<string, mixed>|null $schedule */
    public static function isOpen(?array $schedule, CarbonInterface $now): bool
    {
        if (! $schedule) {
            return true;
        }

        $days = $schedule['days'] ?? [];
        $from = $schedule['from'] ?? null;
        $to = $schedule['to'] ?? null;
        $minute = $now->hour * 60 + $now->minute;

        if ($from === null || $to === null) {
            return $days === [] || in_array($now->dayOfWeekIso, $days, true);
        }

        [$start, $end] = [self::minutes($from), self::minutes($to)];

        if ($start <= $end) {
            return ($days === [] || in_array($now->dayOfWeekIso, $days, true)) && $minute >= $start && $minute < $end;
        }

        // Past midnight: the late part belongs to the day it started on.
        if ($minute >= $start) {
            return $days === [] || in_array($now->dayOfWeekIso, $days, true);
        }

        return $minute < $end && ($days === [] || in_array($now->copy()->subDay()->dayOfWeekIso, $days, true));
    }

    /** A short human line for lists, e.g. "Mon, Tue · 12:00–15:00". @param array<string, mixed>|null $schedule */
    public static function describe(?array $schedule, array $dayNames): string
    {
        if (! $schedule) {
            return '';
        }

        $parts = [];

        if (! empty($schedule['days'])) {
            $parts[] = implode(', ', array_map(fn ($d) => $dayNames[$d - 1] ?? $d, $schedule['days']));
        }

        if (! empty($schedule['from'])) {
            $parts[] = $schedule['from'].'–'.$schedule['to'];
        }

        return implode(' · ', $parts);
    }

    private static function time(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : null;
    }

    private static function minutes(string $time): int
    {
        return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    }
}
