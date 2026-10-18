<?php

declare(strict_types=1);

use App\Models\RouteStop;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use Database\Seeders\DemoSeeder;

/**
 * The demo company's history has to match its plans: every visit on its plan's
 * day, finished through that day's route stop, and nothing in the past left
 * pending. (It once wrote visits "7 and 14 days ago", on the wrong weekday and
 * with no stop.)
 */
beforeEach(function () {
    $this->seed(DemoSeeder::class);
    $this->tenant = Tenant::query()->where('slug', 'demo')->firstOrFail();
    app()->instance('tenant', $this->tenant);
    app()->instance('tenant_id', $this->tenant->id);
});

test('every demo visit sits on its plan\'s day and closes that day\'s stop', function () {
    $visits = ServiceVisit::query()->with(['routeStop.route', 'serviceSubscription'])->get();
    expect($visits)->toHaveCount(16); // 8 pools × 2 visits

    foreach ($visits as $visit) {
        $stop = $visit->routeStop;
        expect($stop)->not->toBeNull()
            ->and($stop?->status)->toBe('completed')
            ->and($stop?->pool_id)->toBe($visit->pool_id)
            ->and($stop?->service_subscription_id)->toBe($visit->service_subscription_id);

        $day = $visit->completed_at?->copy()->setTimezone($this->tenant->timezone);
        expect($day?->toDateString())->toBe($stop?->route?->scheduled_date->toDateString())
            ->and(strtolower((string) $day?->format('l')))->toBe($visit->serviceSubscription?->preferred_day);
    }
});

test('nothing in the demo company\'s past is left pending', function () {
    $pastPending = RouteStop::query()
        ->where('status', 'pending')
        ->whereHas('route', fn ($q) => $q->whereDate('scheduled_date', '<', $this->tenant->today()))
        ->count();

    expect($pastPending)->toBe(0);
});
