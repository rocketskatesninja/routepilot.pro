<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * People screen: the unified customer+agent list (PersonListBuilder),
 * type filtering, the discriminated drawer, tenant isolation, and the
 * staff-only gate.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create(); // tenant_admin
});

test('the list unions this tenant\'s customers and agents', function () {
    Customer::factory()->for($this->tenant)->count(3)->create();
    User::factory()->agent()->for($this->tenant)->count(2)->create();

    // Noise that must not appear: another tenant, and a tenant_admin (not an agent).
    $other = Tenant::factory()->create();
    Customer::factory()->for($other)->create();
    User::factory()->agent()->for($other)->create();
    User::factory()->for($this->tenant)->create(); // tenant_admin — excluded from People

    $this->actingAs($this->admin)
        ->get('/people')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/Index')
            ->has('people.data', 5)
            ->where('counts.all', 5)
            ->where('counts.customers', 3)
            ->where('counts.agents', 2)
        );
});

test('the type filter narrows to agents only', function () {
    Customer::factory()->for($this->tenant)->count(3)->create();
    User::factory()->agent()->for($this->tenant)->count(2)->create();

    $this->actingAs($this->admin)
        ->get('/people?type=agents')
        ->assertInertia(fn (Assert $page) => $page
            ->has('people.data', 2)
            ->where('people.data.0.person_type', 'agent')
        );
});

test('search matches across first and last name (no SQL CONCAT)', function () {
    Customer::factory()->for($this->tenant)->create(['first_name' => 'Jonathan', 'last_name' => 'Smith']);
    Customer::factory()->for($this->tenant)->create(['first_name' => 'Alice', 'last_name' => 'Jones']);

    $this->actingAs($this->admin)
        ->get('/people?search=Smith')
        ->assertInertia(fn (Assert $page) => $page
            ->has('people.data', 1)
            ->where('people.data.0.last_name', 'Smith')
        );
});

test('a customer drawer carries pools and recent visits', function () {
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create(['name' => 'Smith Pool']);
    ServiceVisit::factory()->for($this->tenant)->for($pool)->create([
        'agent_id' => User::factory()->agent()->for($this->tenant)->create()->id,
        'status' => 'completed',
    ]);

    $this->actingAs($this->admin)
        ->get("/people?selected={$customer->id}&selected_type=customer")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.type', 'customer')
            ->where('selected.id', $customer->id)
            ->has('selected.pools', 1)
            ->has('selected.recent_visits', 1)
        );
});

test('an agent drawer carries activity stats', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    ServiceVisit::factory()->for($this->tenant)->for($pool)->count(2)->create([
        'agent_id' => $agent->id, 'status' => 'completed', 'completed_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->get("/people?selected={$agent->id}&selected_type=agent")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.type', 'agent')
            ->where('selected.stats.completed_visits', 2)
            ->where('selected.stats.this_week', 2)
        );
});

test('a foreign-tenant agent does not leak through the drawer', function () {
    $other = Tenant::factory()->create();
    $foreignAgent = User::factory()->agent()->for($other)->create();

    $this->actingAs($this->admin)
        ->get("/people?selected={$foreignAgent->id}&selected_type=agent")
        ->assertInertia(fn (Assert $page) => $page->where('selected', null));
});

test('customers are denied the People screen', function () {
    $portalUser = User::factory()->customer()->for($this->tenant)->create();

    $this->actingAs($portalUser)->get('/people')->assertForbidden();
});
