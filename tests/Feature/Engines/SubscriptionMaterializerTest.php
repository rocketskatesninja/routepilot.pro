<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionMaterializer;
use Illuminate\Support\Carbon;

/**
 * Cadence projection + idempotency for the subscription materializer.
 * Window: "today" is pinned to Monday 2026-06-08; the default horizon
 * is 8 weeks (through Monday 2026-08-03).
 */
beforeEach(function () {
    Carbon::setTestNow('2026-06-08 08:00:00'); // a Monday

    $this->tenant = Tenant::factory()->create();
    $this->agent = User::factory()->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $this->serviceType = ServiceType::factory()->for($this->tenant)->create();
    $this->materializer = new SubscriptionMaterializer;
});

afterEach(fn () => Carbon::setTestNow());

function makeSub(array $attrs = []): ServiceSubscription
{
    $t = test();

    return ServiceSubscription::factory()
        ->for($t->tenant)->for($t->pool)->for($t->serviceType)
        ->create(['assigned_agent_id' => $t->agent->id, ...$attrs]);
}

test('a weekly subscription materializes one stop per preferred weekday in the window', function () {
    makeSub(['frequency' => 'weekly', 'preferred_day' => 'tuesday']);

    $this->materializer->run($this->tenant->id);

    // Tuesdays from Jun 9 through Aug 3 2026 inclusive = 8 occurrences.
    $stops = RouteStop::with('route')->get();
    expect($stops)->toHaveCount(8)
        ->and($stops->first()->route->scheduled_date->toDateString())->toBe('2026-06-09')
        ->and($stops->every(fn ($s) => $s->route->scheduled_date->isTuesday()))->toBeTrue()
        ->and($stops->every(fn ($s) => $s->route->agent_id === $this->agent->id))->toBeTrue();
});

test('re-running is idempotent', function () {
    makeSub(['frequency' => 'weekly', 'preferred_day' => 'tuesday']);

    $this->materializer->run($this->tenant->id);
    $this->materializer->run($this->tenant->id);

    expect(RouteStop::count())->toBe(8);
});

test('biweekly subscriptions follow the week_type parity', function () {
    // 2026-06-09 falls in ISO week 24 (even).
    makeSub(['frequency' => 'biweekly', 'preferred_day' => 'tuesday', 'frequency_details' => ['week_type' => 'even']]);

    $this->materializer->run($this->tenant->id);

    $dates = RouteStop::with('route')->get()->map(fn ($s) => $s->route->scheduled_date->toDateString())->all();
    expect($dates)->toBe(['2026-06-09', '2026-06-23', '2026-07-07', '2026-07-21']);
});

test('monthly subscriptions clamp day_of_month to the month length', function () {
    makeSub(['frequency' => 'monthly', 'frequency_details' => ['day_of_month' => 31]]);

    $this->materializer->run($this->tenant->id);

    $dates = RouteStop::with('route')->get()->map(fn ($s) => $s->route->scheduled_date->toDateString())->all();
    // June has 30 days (clamped); July's 31st is inside the window.
    expect($dates)->toBe(['2026-06-30', '2026-07-31']);
});

test('dates inside a vacation hold are skipped and resume after', function () {
    makeSub([
        'frequency' => 'weekly', 'preferred_day' => 'tuesday',
        'hold_starts_at' => '2026-06-15', 'hold_ends_at' => '2026-06-21',
    ]);

    $this->materializer->run($this->tenant->id);

    $dates = RouteStop::with('route')->get()->map(fn ($s) => $s->route->scheduled_date->toDateString());
    expect($dates)->toHaveCount(7)             // 8 Tuesdays minus the held one
        ->and($dates)->not->toContain('2026-06-16')
        ->and($dates)->toContain('2026-06-09') // before the hold
        ->and($dates)->toContain('2026-06-23'); // auto-resumed after
});

test('two subscriptions for one pool with different agents both materialize on the same day', function () {
    $agentB = User::factory()->create();
    makeSub(['frequency' => 'weekly', 'preferred_day' => 'tuesday']);
    ServiceSubscription::factory()
        ->for($this->tenant)->for($this->pool)->for($this->serviceType)
        ->create(['assigned_agent_id' => $agentB->id, 'frequency' => 'weekly', 'preferred_day' => 'tuesday']);

    $this->materializer->run($this->tenant->id);

    $june9Routes = Route::withoutGlobalScopes()->whereDate('scheduled_date', '2026-06-09')->get();
    expect($june9Routes)->toHaveCount(2)
        ->and(RouteStop::whereIn('route_id', $june9Routes->pluck('id'))->count())->toBe(2);
});

test('paused and foreign-tenant subscriptions do not materialize', function () {
    makeSub(['status' => 'paused']);

    $other = Tenant::factory()->create();
    $otherCustomer = Customer::factory()->for($other)->create();
    $otherPool = Pool::factory()->for($other)->for($otherCustomer)->create();
    $otherType = ServiceType::factory()->for($other)->create();
    ServiceSubscription::factory()->for($other)->for($otherPool)->for($otherType)
        ->create(['assigned_agent_id' => $this->agent->id]);

    $this->materializer->run($this->tenant->id);

    expect(RouteStop::count())->toBe(0);
});

test('agentless subscriptions materialize onto the per-day unassigned route', function () {
    // No assigned agent: the office still needs these to surface, so they land
    // on the agent_id-null "unassigned" route to be dragged onto an agent later.
    makeSub(['assigned_agent_id' => null, 'frequency' => 'weekly', 'preferred_day' => 'tuesday']);

    $this->materializer->run($this->tenant->id);

    $stops = RouteStop::with('route')->get();
    expect($stops)->toHaveCount(8)
        ->and($stops->every(fn ($s) => $s->route->agent_id === null))->toBeTrue();
});
