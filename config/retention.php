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

    // Finalize a customer erasure this many days after they were soft-deleted:
    // the customer (and — via DB FK cascade — their pools, visits, readings,
    // treatments, photos and charges) is hard-deleted, photo files included.
    'customer_purge_days' => (int) env('RETENTION_CUSTOMER_DAYS', 365),

    // Prune read in-app notifications older than this many days (housekeeping).
    'read_notification_days' => (int) env('RETENTION_NOTIFICATION_DAYS', 90),

];
