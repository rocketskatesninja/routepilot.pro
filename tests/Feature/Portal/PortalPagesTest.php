<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceLocation;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow('2026-10-15 14:00:00'); // Thursday morning in New York
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);

    $this->user = User::factory()->customer()->for($this->tenant)->create(['dashboard_style' => 'simple']); // the Home page, not the grid
    $this->agent = User::factory()->agent()->for($this->tenant)->create(['first_name' => 'Marcus']);
    $this->customer = Customer::factory()->for($this->tenant)->create(['first_name' => 'Robert']);
    $this->customer->forceFill(['user_id' => $this->user->id])->save();
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create([
        'name' => 'Backyard pool', 'volume_gallons' => 12000, 'sanitizer_type' => 'salt', 'has_heater' => true,
        'custom_target_ranges' => ['ph' => ['min' => 7.4, 'max' => 7.8]],
    ]);
    $this->service = ServiceType::factory()->for($this->tenant)->create(['name' => 'Weekly Pool Service']);
    $this->plan = $this->pool->subscriptions()->create([
        'service_type_id' => $this->service->id, 'assigned_agent_id' => $this->agent->id, 'frequency' => 'weekly', 'preferred_day' => 'friday', 'status' => 'active',
    ]);
});

function portalVisit(Pool $pool, User $agent, string $completedAt, array $reading = ['free_chlorine' => 2.5, 'ph' => 7.5]): ServiceVisit
{
    $visit = ServiceVisit::factory()->for($pool->tenant)->for($pool)->create(['status' => 'completed', 'completed_at' => $completedAt, 'agent_id' => $agent->id]);
    $visit->chemicalReading()->create($reading);

    return $visit;
}

test('a customer lands on their Home page, not the staff dashboard grid', function () {
    portalVisit($this->pool, $this->agent, '2026-10-10 15:00:00');

    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('portal/Home')
            ->where('firstName', 'Robert')
            ->where('hasService', true)
            ->where('lastVisit.agent', 'Marcus')
            ->where('lastVisit.on', '2026-10-10')
            ->where('water.0.pool', 'Backyard pool')
            ->where('water.0.reading.ph', 7.5)
            ->where('water.0.targets.ph.min', 7.4)
            ->where('pendingRequests', 0));
});

test('Home leads with the next visit: who, when, and what for', function () {
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => '2026-10-16']);
    RouteStop::factory()->for($route)->for($this->pool)->create([
        'service_subscription_id' => $this->plan->id, 'estimated_arrival' => Carbon::parse('2026-10-16 14:20', $this->tenant->timezone)->utc(),
    ]);
    $later = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => '2026-10-23']);
    RouteStop::factory()->for($later)->for($this->pool)->create(['service_subscription_id' => $this->plan->id]);

    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->has('upcoming', 2)
            ->where('upcoming.0.date', '2026-10-16')
            ->where('upcoming.0.agent', 'Marcus')
            ->where('upcoming.0.window', '2:00 – 3:00 PM')
            ->where('upcoming.0.services', ['Weekly Pool Service'])
            ->where('upcoming.1.date', '2026-10-23'));
});

test('Home shows nothing upcoming and no service for a new customer', function () {
    $this->plan->forceFill(['status' => 'cancelled'])->save();

    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('upcoming', [])->where('hasService', false)->where('lastVisit', null)->where('water', []));
});

test('Home never shows another customer\'s visits', function () {
    $other = Pool::factory()->for($this->tenant)->for(Customer::factory()->for($this->tenant))->create();
    portalVisit($other, $this->agent, '2026-10-12 15:00:00');

    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('lastVisit', null)->where('water', []));
});

test('My Pools describes each pool, its latest water test against its own targets, and its service', function () {
    portalVisit($this->pool, $this->agent, '2026-10-10 15:00:00', ['free_chlorine' => 3.1, 'ph' => 7.2, 'salt' => 3200]);

    $this->actingAs($this->user)
        ->get('/my-pools')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('portal/Pools')
            ->where('pools.0.name', 'Backyard pool')
            ->where('pools.0.volume', 12000)
            ->where('pools.0.sanitizer', 'salt')
            ->where('pools.0.features', ['heater'])
            ->where('pools.0.tested_on', '2026-10-10')
            ->where('pools.0.reading.salt', 3200)
            ->where('pools.0.targets.ph.max', 7.8)
            ->where('pools.0.plans.0.service', 'Weekly Pool Service')
            ->where('pools.0.plans.0.preferred_day', 'friday')
            ->where('pools.0.plans.0.agent', 'Marcus'));
});

test('Balance names the service on each visit charge and lists recent payments', function () {
    $visit = portalVisit($this->pool, $this->agent, '2026-10-10 15:00:00');
    $visit->forceFill(['service_subscription_id' => $this->plan->id])->save();
    app(BillingService::class)->chargeVisit($visit);
    Payment::query()->create(['customer_id' => $this->customer->id, 'amount' => 25, 'method' => 'card', 'status' => 'succeeded'])
        ->forceFill(['tenant_id' => $this->tenant->id, 'paid_at' => Carbon::parse('2026-10-12 16:00')])->save();

    $this->actingAs($this->user)
        ->get('/balance')
        ->assertInertia(fn (Assert $page) => $page->component('portal/Balance')
            ->where('visits.0.service', 'Weekly Pool Service')
            ->where('visits.0.pool', 'Backyard pool')
            ->where('payments.0.amount', 25)
            ->where('payments.0.on', '2026-10-12'));
});

test('with two pools, Home and My Pools say which pool is which', function () {
    $beach = Pool::factory()->for($this->tenant)->for($this->customer)->create(['name' => 'Beach House Pool']);
    ServiceLocation::factory()->for($beach)->create(['address_line1' => '27 Marsh Hen Lane', 'city' => 'St. Simons Island']);
    $beachPlan = $beach->subscriptions()->create([
        'service_type_id' => $this->service->id, 'assigned_agent_id' => $this->agent->id, 'frequency' => 'biweekly', 'preferred_day' => 'friday', 'status' => 'active',
    ]);
    $route = Route::factory()->for($this->tenant)->create(['agent_id' => $this->agent->id, 'scheduled_date' => '2026-10-16']);
    RouteStop::factory()->for($route)->for($beach)->create(['service_subscription_id' => $beachPlan->id]);

    $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('poolCount', 2)
            ->where('upcoming.0.pools', ['Beach House Pool']));

    $this->actingAs($this->user)
        ->get('/my-pools')
        ->assertInertia(fn (Assert $page) => $page
            ->has('pools', 2)
            ->where('pools.1.name', 'Beach House Pool')
            ->where('pools.1.address', '27 Marsh Hen Lane, St. Simons Island'));
});
