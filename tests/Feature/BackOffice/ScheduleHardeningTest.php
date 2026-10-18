<?php

declare(strict_types=1);

use App\Events\RouteUpdated;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Chat\Tools\SkipStop as SkipStopTool;
use App\Services\SubscriptionMaterializer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The scheduling system's guard rails: what can be skipped and restored, what
 * happens to a removed tech's work, and the database-level promises behind it.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create(['first_name' => 'Marcus', 'last_name' => 'Bennett']);
    $this->customer = Customer::factory()->for($this->tenant)->create(['first_name' => 'Robert', 'last_name' => 'Anderson']);
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create(['name' => 'Anderson Pool']);
});

/** A stop on the given day's route for the agent (or the unassigned route). */
function hardeningStop(string $date, string $status = 'pending', ?int $agentId = null): RouteStop
{
    $route = Route::forDay(test()->tenant->id, $agentId ?? test()->agent->id, $date);

    return RouteStop::factory()->for($route)->for(test()->pool)->create(['status' => $status, 'stop_order' => 1]);
}

/** An active weekly plan for the pool, worked by the agent, with its stops on the routes. */
function hardeningPlan(): ServiceSubscription
{
    $plan = ServiceSubscription::factory()->for(test()->tenant)->for(test()->pool)->for(ServiceType::factory()->for(test()->tenant))->create([
        'assigned_agent_id' => test()->agent->id, 'frequency' => 'weekly', 'status' => 'active',
        'preferred_day' => strtolower(test()->tenant->today()->addDay()->format('l')),
    ]);
    app(SubscriptionMaterializer::class)->run(test()->tenant->id);

    return $plan;
}

test('a finished stop can\'t be skipped', function () {
    $stop = hardeningStop($this->tenant->today()->toDateString(), 'completed');

    $this->actingAs($this->admin)->post("/stops/{$stop->id}/skip")->assertSessionHas('error');

    expect($stop->fresh()?->status)->toBe('completed');
});

test('putting back a skipped stop from a past day marks it missed, from today on it is due again', function () {
    $past = hardeningStop($this->tenant->today()->subDay()->toDateString(), 'skipped');
    $today = hardeningStop($this->tenant->today()->toDateString(), 'skipped');

    $this->actingAs($this->admin)->post("/stops/{$past->id}/unskip")->assertRedirect();
    $this->actingAs($this->admin)->post("/stops/{$today->id}/unskip")->assertRedirect();

    expect($past->fresh()?->status)->toBe('missed')
        ->and($today->fresh()?->status)->toBe('pending');
});

test('the assistant\'s skip refreshes the live schedule board, like the board\'s own', function () {
    $date = $this->tenant->today()->toDateString();
    $stop = hardeningStop($date);
    Event::fake([RouteUpdated::class]);

    $reply = (new SkipStopTool)->execute(['customer_name' => 'Robert Anderson', 'date' => $date], $this->tenant->id);

    expect($reply)->toContain('Skipped')
        ->and($stop->fresh()?->status)->toBe('skipped');
    Event::assertDispatched(RouteUpdated::class, fn (RouteUpdated $e): bool => $e->date === $date);
});

test('removing a tech hands their plans and upcoming stops back to the office', function () {
    $plan = hardeningPlan();
    $other = User::factory()->agent()->for($this->tenant)->create();
    $theirs = ServiceSubscription::factory()->for($this->tenant)->for(Pool::factory()->for($this->tenant)->for($this->customer))
        ->for(ServiceType::factory()->for($this->tenant))->create(['assigned_agent_id' => $other->id, 'status' => 'active']);

    $this->actingAs($this->admin)->delete("/agents/{$this->agent->id}")->assertRedirect();

    $upcoming = RouteStop::query()->where('service_subscription_id', $plan->id)->with('route')->get();
    expect($plan->fresh()?->assigned_agent_id)->toBeNull()
        ->and($upcoming)->not->toBeEmpty()
        ->and($upcoming->every(fn (RouteStop $s): bool => $s->route->agent_id === null))->toBeTrue()
        ->and($theirs->fresh()?->assigned_agent_id)->toBe($other->id);
});

test('switching a tech off hands their plans back too', function () {
    $plan = hardeningPlan();

    $this->actingAs($this->admin)->patch("/agents/{$this->agent->id}", [
        'first_name' => 'Marcus', 'email' => $this->agent->email, 'is_active' => false,
    ])->assertRedirect();

    expect($plan->fresh()?->assigned_agent_id)->toBeNull()
        ->and(RouteStop::query()->where('service_subscription_id', $plan->id)->whereHas('route', fn ($q) => $q->whereNotNull('agent_id'))->exists())->toBeFalse();
});

test('Generate fills the same window as the nightly job', function () {
    $plan = hardeningPlan();
    RouteStop::query()->delete();

    $this->actingAs($this->admin)->post('/schedule/materialize')->assertSessionHas('success');

    $last = RouteStop::query()->where('service_subscription_id', $plan->id)->with('route')->get()->max(fn (RouteStop $s) => $s->route->scheduled_date);
    expect($last->toDateString())->toBeGreaterThan($this->tenant->today()->addWeeks(SubscriptionMaterializer::WINDOW_WEEKS - 1)->toDateString());
});

test('a past day still shows an archived pool and a removed tech by name', function () {
    $date = $this->tenant->today()->subDay()->toDateString();
    hardeningStop($date, 'completed');
    $this->pool->delete();
    $this->agent->delete();

    $this->actingAs($this->admin)->get("/schedule?date={$date}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('routes.0.agent', 'Marcus Bennett')
            ->where('routes.0.stops.0.pool', 'Anderson Pool'));
});

test('the database keeps one visit per stop and one route per tech per day', function () {
    $stop = hardeningStop($this->tenant->today()->toDateString());
    ServiceVisit::factory()->for($this->tenant)->for($this->pool)->create(['route_stop_id' => $stop->id, 'agent_id' => $this->agent->id]);

    expect(fn () => ServiceVisit::factory()->for($this->tenant)->for($this->pool)->create(['route_stop_id' => $stop->id, 'agent_id' => $this->agent->id]))
        ->toThrow(UniqueConstraintViolationException::class);

    $day = $this->tenant->today()->addDay()->toDateString();
    expect(Route::forDay($this->tenant->id, $this->agent->id, $day)->id)->toBe(Route::forDay($this->tenant->id, $this->agent->id, $day)->id)
        ->and(fn () => Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => $day]))
        ->toThrow(UniqueConstraintViolationException::class);
});
