<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Drive-time ETA model
    |--------------------------------------------------------------------------
    |
    | Used by App\Actions\EstimateArrivals to turn route geometry into per-stop
    | arrival estimates without any external API. A real drive-time API can
    | later replace the haversine model behind the same action.
    |
    */

    // Multiply great-circle (haversine) km by this to approximate road km.
    'road_factor' => (float) env('ROUTING_ROAD_FACTOR', 1.3),

    // Assumed average driving speed (km/h) for leg durations.
    'avg_speed_kmh' => (float) env('ROUTING_AVG_SPEED_KMH', 40),

    // Floor for any drive leg between two stops (minutes) — parking, walk-up, etc.
    'min_drive_minutes' => (int) env('ROUTING_MIN_DRIVE_MINUTES', 5),

    // On-site service time when a stop has no service-type duration (minutes).
    'default_service_minutes' => (int) env('ROUTING_DEFAULT_SERVICE_MINUTES', 30),

    // Fallback workday start ("HH:MM") when neither the route nor the tenant set one.
    'day_start' => (string) env('ROUTING_DAY_START', '08:00'),

];
