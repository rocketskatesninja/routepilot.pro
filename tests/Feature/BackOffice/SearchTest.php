<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
});

test('search returns tenant-scoped grouped matches', function () {
    $customer = Customer::factory()->for($this->tenant)->create(['first_name' => 'Marcus', 'last_name' => 'Bennett']);
    Pool::factory()->for($this->tenant)->for($customer)->create(['name' => 'Marcus Backyard']);

    // A same-named customer in another tenant must NOT leak in.
    $other = Tenant::factory()->create();
    Customer::factory()->for($other)->create(['first_name' => 'Marcus', 'last_name' => 'Foreign']);

    $this->actingAs($this->admin)
        ->getJson('/search?q=Marcus')
        ->assertOk()
        ->assertJsonPath('groups.0.label', 'Customers')
        ->assertJsonPath('groups.0.items.0.label', 'Marcus Bennett')
        ->assertJsonPath('groups.0.items.0.url', "/people?selected={$customer->id}&selected_type=customer")
        ->assertJsonMissing(['label' => 'Marcus Foreign']);
});

test('short queries return nothing', function () {
    $this->actingAs($this->admin)->getJson('/search?q=a')->assertOk()->assertExactJson(['groups' => []]);
});

test('customers cannot use the search endpoint', function () {
    $portal = User::factory()->customer()->for($this->tenant)->create();

    $this->actingAs($portal)->getJson('/search?q=marcus')->assertForbidden();
});
