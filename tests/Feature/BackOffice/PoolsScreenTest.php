<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceLocation;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Pools back-office screen: tenant isolation, the URL-driven drawer, and
 * the staff-only gate. Tenant scoping is enforced by ResolveTenant binding
 * the session user's tenant + the global TenantScope.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(); // role tenant_admin by default
    $this->customer = Customer::factory()->for($this->tenant)->create();
});

test('guests are redirected to login', function () {
    $this->get('/pools')->assertRedirect('/login');
});

test('a tenant admin sees only their own tenant\'s pools', function () {
    Pool::factory()->for($this->tenant)->for($this->customer)->create(['name' => 'Smith Pool']);
    Pool::factory()->for($this->tenant)->for($this->customer)->create(['name' => 'Lee Pool']);

    // Another tenant's pool must not leak in.
    $other = Tenant::factory()->create();
    $otherCustomer = Customer::factory()->for($other)->create();
    Pool::factory()->for($other)->for($otherCustomer)->create(['name' => 'Foreign Pool']);

    $this->actingAs($this->admin)
        ->get('/pools')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('pools/Index')
            ->has('pools.data', 2)
            ->where('pools.total', 2)
            ->where('selected', null)
        );
});

test('search filters the list', function () {
    Pool::factory()->for($this->tenant)->for($this->customer)->create(['name' => 'Smith Pool']);
    Pool::factory()->for($this->tenant)->for($this->customer)->create(['name' => 'Lee Pool']);

    $this->actingAs($this->admin)
        ->get('/pools?search=Smith')
        ->assertInertia(fn (Assert $page) => $page
            ->has('pools.data', 1)
            ->where('pools.data.0.name', 'Smith Pool')
            ->where('filters.search', 'Smith')
        );
});

test('?selected loads that pool into the drawer prop', function () {
    $pool = Pool::factory()->for($this->tenant)->for($this->customer)->create([
        'name' => 'Smith Pool', 'volume_gallons' => 18000,
    ]);

    $this->actingAs($this->admin)
        ->get('/pools?selected='.$pool->id)
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.id', $pool->id)
            ->where('selected.name', 'Smith Pool')
            ->where('selected.volume_gallons', 18000)
            ->where('selected.customer.name', trim($this->customer->first_name.' '.$this->customer->last_name))
        );
});

test('the pool panel lists its missed visits, newest first', function () {
    $pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    foreach (['2026-10-05' => 'missed', '2026-10-12' => 'missed', '2026-09-28' => 'missed', '2026-10-19' => 'pending'] as $date => $status) {
        $route = Route::factory()->for($this->tenant)->create(['scheduled_date' => $date]);
        RouteStop::factory()->for($route)->for($pool)->create(['status' => $status]);
    }

    $this->actingAs($this->admin)
        ->get('/pools?selected='.$pool->id)
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.missed.count', 3)
            ->where('selected.missed.recent.0.on', '2026-10-12')
            ->where('selected.missed.recent.2.on', '2026-09-28')
        );
});

test('a foreign pool id does not leak through ?selected', function () {
    $other = Tenant::factory()->create();
    $otherCustomer = Customer::factory()->for($other)->create();
    $foreign = Pool::factory()->for($other)->for($otherCustomer)->create();

    $this->actingAs($this->admin)
        ->get('/pools?selected='.$foreign->id)
        ->assertInertia(fn (Assert $page) => $page->where('selected', null));
});

test('customers are denied the back-office screen', function () {
    $portalUser = User::factory()->customer()->for($this->tenant)->create();

    $this->actingAs($portalUser)->get('/pools')->assertForbidden();
});

test('the pool panel has the full latest water test, dated by its visit, and the address', function () {
    app()->instance('tenant_id', $this->tenant->id);
    $pool = Pool::factory()->for($this->tenant)->for($this->customer)->create(['has_cover' => true]);
    $pool->serviceLocation()->create(['address_line1' => '27 Marsh Hen Lane', 'city' => 'St. Simons Island']);
    $visit = ServiceVisit::factory()->for($this->tenant)->for($pool)->create([
        'status' => 'completed', 'completed_at' => '2026-10-15 17:30:00',
        'agent_id' => User::factory()->agent()->for($this->tenant)->create()->id,
    ]);
    $visit->chemicalReading()->create(['free_chlorine' => 2.6, 'ph' => 7.4, 'calcium_hardness' => 290, 'cyanuric_acid' => 50]);

    $this->actingAs($this->admin)
        ->get('/pools?selected='.$pool->id)
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.latest_reading.taken_on', '2026-10-15')
            ->where('selected.latest_reading.calcium_hardness', 290)
            ->where('selected.latest_reading.cyanuric_acid', 50)
            ->where('selected.location.address', '27 Marsh Hen Lane')
            ->where('selected.features', ['cover'])
        );
});

test('“Getting there” carries the full address and map position, for the Google Maps link', function () {
    $pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    ServiceLocation::factory()->create([
        'pool_id' => $pool->id, 'address_line1' => '27 Marsh Hen Lane', 'city' => 'St. Simons Island', 'state' => 'GA', 'zip' => '31522',
        'lat' => 31.15, 'lng' => -81.39,
    ]);

    $this->actingAs($this->admin)->get('/pools?selected='.$pool->id)
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.location.state', 'GA')
            ->where('selected.location.zip', '31522')
            ->where('selected.location.coords', [31.15, -81.39]));
});
