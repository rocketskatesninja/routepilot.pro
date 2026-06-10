<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly automation (platform-wide; each command iterates active tenants).
Schedule::command('app:backup-database')->dailyAt('01:00');
Schedule::command('app:materialize-schedules')->dailyAt('02:00');
Schedule::command('app:flag-overdue-invoices')->dailyAt('03:00');
Schedule::command('app:generate-invoices')->monthlyOn(1, '04:00');
