<?php

namespace App\Modules\Orders\Support;

/** Small map maths: is a point inside an area, how far apart are two points, and cleaning up a drawn area. */
final class Geo
{
    public const MAX_VERTICES = 200;

    /**
     * Ray casting. $polygon is a list of [lat, lng] pairs.
     *
     * @param  list<array{0: float, 1: float}>  $polygon
     */
    public static function inside(float $lat, float $lng, array $polygon): bool
    {
        $n = count($polygon);

        if ($n < 3) {
            return false;
        }

        $inside = false;

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$yi, $xi] = $polygon[$i];
            [$yj, $xj] = $polygon[$j];

            if ((($yi > $lat) !== ($yj > $lat)) && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /** Great-circle distance in kilometres. */
    public static function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)), 3);
    }

    /**
     * What a browser sent as an area, or null when it is not a usable one.
     *
     * @return list<array{0: float, 1: float}>|null
     */
    public static function cleanPolygon(mixed $raw): ?array
    {
        $points = is_string($raw) ? json_decode($raw, true) : $raw;

        if (! is_array($points) || count($points) < 3 || count($points) > self::MAX_VERTICES) {
            return null;
        }

        $clean = [];

        foreach ($points as $p) {
            if (! is_array($p) || count($p) < 2 || ! is_numeric($p[0]) || ! is_numeric($p[1])) {
                return null;
            }

            [$lat, $lng] = [round((float) $p[0], 6), round((float) $p[1], 6)];

            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                return null;
            }

            $clean[] = [$lat, $lng];
        }

        return $clean;
    }

    public static function validPoint(mixed $lat, mixed $lng): bool
    {
        return is_numeric($lat) && is_numeric($lng) && abs((float) $lat) <= 90 && abs((float) $lng) <= 180 && ! ((float) $lat === 0.0 && (float) $lng === 0.0);
    }
}
