<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceLocation;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['status' => 'active', 'settings' => ['hq_lat' => 28.0, 'hq_lng' => -82.10]]);
    app()->instance('tenant_id', $this->tenant->id);
    $this->agent = User::factory()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
});

/** A one-stop route on the given date/status with a located pool. */
function routeOn(Tenant $tenant, User $agent, Customer $customer, string $date, string $status): RouteStop
{
    $route = Route::factory()->for($tenant)->create([
        'agent_id' => $agent->id, 'scheduled_date' => $date, 'status' => $status, 'start_time' => '08:00',
    ]);
    $pool = Pool::factory()->for($tenant)->for($customer)->create();
    ServiceLocation::factory()->for($pool)->create(['lat' => 28.0, 'lng' => -82.09]);

    return RouteStop::factory()->for($route)->for($pool)->create(['stop_order' => 1]);
}

test('the command estimates arrivals for upcoming routes', function () {
    $stop = routeOn($this->tenant, $this->agent, $this->customer, today()->addDay()->toDateString(), 'scheduled');

    $this->artisan('app:estimate-arrivals')->assertSuccessful();

    expect($stop->fresh()->estimated_arrival?->format('H:i'))->toBe('08:05');
});

test('past routes are left untouched', function () {
    $stop = routeOn($this->tenant, $this->agent, $this->customer, today()->subDay()->toDateString(), 'scheduled');

    $this->artisan('app:estimate-arrivals')->assertSuccessful();

    expect($stop->fresh()->estimated_arrival)->toBeNull();
});
