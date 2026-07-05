<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Drain the queue (campaign emails etc.) — runs ~once a minute, exits when empty.
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3')->everyMinute()->withoutOverlapping();

// Keep the dashboard weather cache warm so visitor loads never call the API inline.
Schedule::command('app:warm-weather')->everyThirtyMinutes()->withoutOverlapping();

// Nightly automation (platform-wide; each command iterates active tenants).
Schedule::command('app:backup-database')->dailyAt('01:00');
Schedule::command('app:materialize-schedules')->dailyAt('02:00');
Schedule::command('app:estimate-arrivals')->dailyAt('02:15'); // drive-time ETAs for the freshly materialized routes
Schedule::command('app:flag-overdue-invoices')->dailyAt('03:00');
Schedule::command('app:geocode-locations')->dailyAt('03:30'); // retry any locations missing coordinates
Schedule::command('app:generate-invoices')->monthlyOn(1, '04:00');
Schedule::command('app:charge-autopay')->monthlyOn(2, '05:00')->withoutOverlapping();
Schedule::command('app:retry-autopay')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('app:reconcile-stripe-payments')->hourly()->withoutOverlapping(); // settle Checkout payments a webhook missed
Schedule::command('app:daily-ops-alerts')->dailyAt('07:00'); // proactive admin alerts (after the night's routing/billing settle)
Schedule::command('app:purge-retention')->weeklyOn(1, '04:30'); // GDPR erasure finalize + read-notification prune (low-traffic weekly sweep)
