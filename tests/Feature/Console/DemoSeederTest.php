<?php

declare(strict_types=1);

use App\Models\ChemicalInventory;
use App\Models\RouteStop;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Support\DemoParts;
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
    $visits = ServiceVisit::query()->with(['routeStop.route', 'serviceSubscription.serviceType', 'tasks'])->get();
    expect($visits)->toHaveCount(16); // 8 pools × 2 visits

    foreach ($visits as $visit) {
        $stop = $visit->routeStop;
        expect($stop)->not->toBeNull()
            ->and($stop?->status)->toBe('completed')
            ->and($stop?->pool_id)->toBe($visit->pool_id)
            ->and($stop?->service_subscription_id)->toBe($visit->service_subscription_id);

        // Its report lists the plan's checklist, ticked off.
        expect($visit->tasks->pluck('task_name')->all())->toBe($visit->serviceSubscription?->serviceType?->tasks)
            ->and($visit->tasks->every->is_completed)->toBeTrue();

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

test('the demo company keeps common repair parts, stocked through the log, with one running low', function () {
    $parts = ChemicalInventory::query()->parts()->get();

    expect($parts)->toHaveCount(count(DemoParts::LIST))
        ->and($parts->every(fn ($p) => $p->unit === 'unit' && $p->part_number === null))->toBeTrue()
        ->and((float) $parts->firstWhere('chemical_name', 'Pump lid O-ring')?->current_stock)->toBe(8.0)
        ->and($parts->firstWhere('chemical_name', 'Pump lid O-ring')?->transactions()->sole()->only(['type', 'quantity']))->toBe(['type' => 'restock', 'quantity' => '8.00'])
        ->and($parts->filter->isLowStock()->pluck('chemical_name')->all())->toBe(['Skimmer lid'])
        ->and($parts->firstWhere('chemical_name', 'Thread seal tape')?->sell_price)->toBeNull();
});
