<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Data retention windows
    |--------------------------------------------------------------------------
    |
    | Used by the App\Services\DataRetention sweep (app:purge-retention).
    | Set a value to 0 to disable that pass entirely.
    |
    */

    // Archived customers are never deleted automatically (their reports and
    // billing records stay); People → Archived → Delete permanently does it by hand.

    // Prune read in-app notifications older than this many days (housekeeping).
    'read_notification_days' => (int) env('RETENTION_NOTIFICATION_DAYS', 90),

    // Prune a tech's last-known position once it's this old (one row per tech;
    // the live map only shows the last 30 minutes).
    'agent_location_days' => (int) env('RETENTION_AGENT_LOCATION_DAYS', 7),

];
