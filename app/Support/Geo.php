<?php

declare(strict_types=1);

namespace App\Support;

/** Distance between two [lat, lng] points, for the route optimizer. */
final class Geo
{
    /**
     * Haversine great-circle distance in km. Driving distance is typically
     * 1.2–1.4× this; callers apply a road factor where they need drive time.
     *
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    public static function km(array $a, array $b): float
    {
        $lat1 = deg2rad($a[0]);
        $lat2 = deg2rad($b[0]);
        $dLat = deg2rad($b[0] - $a[0]);
        $dLng = deg2rad($b[1] - $a[1]);

        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return 6371.0 * 2 * atan2(sqrt($h), sqrt(1 - $h));
    }
}
