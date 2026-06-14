<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Services screen: the tenant's service-type catalog, the drawer template
 * (modules + task checklist + active-pool count), tenant isolation, and the
 * staff-only gate.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->for($this->tenant)->create();
});

test('lists this tenant\'s service types only', function () {
    ServiceType::factory()->for($this->tenant)->count(2)->create();

    $other = Tenant::factory()->create();
    ServiceType::factory()->for($other)->create();

    $this->actingAs($this->admin)
        ->get('/services')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('services/Index')
            ->has('services.data', 2)
        );
});

test('the drawer carries the template detail and active-pool count', function () {
    $type = ServiceType::factory()->for($this->tenant)->create([
        'name' => 'Weekly Pool Service',
        'price' => 50,
        'estimated_duration_minutes' => 30,
        'field_modules' => ['tasks' => true, 'chemistry' => true, 'treatments' => true, 'photos' => false],
        'tasks' => ['Skim surface', 'Brush walls', 'Empty baskets'],
    ]);

    // One active subscription using this type.
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    ServiceSubscription::factory()->for($this->tenant)->for($pool)->for($type)->create([
        'assigned_agent_id' => User::factory()->agent()->for($this->tenant)->create()->id,
        'status' => 'active',
    ]);

    $this->actingAs($this->admin)
        ->get("/services?selected={$type->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.id', $type->id)
            ->where('selected.name', 'Weekly Pool Service')
            ->where('selected.duration_minutes', 30)
            ->where('selected.pools', 1)
            ->where('selected.modules', ['Tasks', 'Chemistry', 'Treatments']) // photos off
            ->has('selected.tasks', 3)
        );
});

test('a foreign service type does not leak through the drawer', function () {
    $other = Tenant::factory()->create();
    $foreign = ServiceType::factory()->for($other)->create();

    $this->actingAs($this->admin)
        ->get("/services?selected={$foreign->id}")
        ->assertInertia(fn (Assert $page) => $page->where('selected', null));
});

test('customers are denied the Services screen', function () {
    $portalUser = User::factory()->customer()->for($this->tenant)->create();

    $this->actingAs($portalUser)->get('/services')->assertForbidden();
});

test('column sort orders the list and is reported back to the view', function () {
    ServiceType::factory()->for($this->tenant)->create(['name' => 'Alpha', 'price' => 50]);
    ServiceType::factory()->for($this->tenant)->create(['name' => 'Zulu', 'price' => 10]);

    $this->actingAs($this->admin)
        ->get('/services?sort=name&dir=desc')
        ->assertInertia(fn (Assert $page) => $page
            ->where('sort', ['key' => 'name', 'dir' => 'desc'])
            ->where('services.data.0.name', 'Zulu')
            ->where('services.data.1.name', 'Alpha')
        );

    $this->actingAs($this->admin)
        ->get('/services?sort=price&dir=asc')
        ->assertInertia(fn (Assert $page) => $page->where('services.data.0.name', 'Zulu')); // cheapest first
});

test('an unknown sort key falls back to the default (no arbitrary column)', function () {
    ServiceType::factory()->for($this->tenant)->create(['name' => 'Beta']);

    $this->actingAs($this->admin)
        ->get('/services?sort=price);DROP+TABLE&dir=desc')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('sort.key', 'name'));
});
