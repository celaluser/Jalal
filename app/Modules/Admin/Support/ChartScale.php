<?php

namespace App\Modules\Admin\Support;

/**
 * Picks clean axis ticks (0 / 1,000 / 2,000 ...) for a chart's maximum value.
 */
final class ChartScale
{
    /**
     * @return array{max: float, ticks: list<float>}
     */
    public static function nice(float $maxValue, int $tickCount = 4, bool $integer = false): array
    {
        if ($maxValue <= 0) {
            return ['max' => 1.0, 'ticks' => [0.0, 1.0]];
        }

        $rough = $maxValue / $tickCount;
        $magnitude = 10 ** floor(log10($rough));
        $residual = $rough / $magnitude;
        $step = $magnitude * match (true) {
            $residual <= 1 => 1,
            $residual <= 2 => 2,
            $residual <= 5 => 5,
            default => 10,
        };

        // Counts cannot have fractional ticks (0, 0.5, 1 would all print as "1").
        if ($integer) {
            $step = max(1.0, ceil($step));
        }

        $max = ceil($maxValue / $step) * $step;
        $ticks = [];

        for ($v = 0.0; $v <= $max + $step / 1000; $v += $step) {
            $ticks[] = round($v, 10);
        }

        return ['max' => (float) $max, 'ticks' => $ticks];
    }
}
