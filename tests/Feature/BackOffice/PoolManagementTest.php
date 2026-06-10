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
    $this->customer = Customer::factory()->for($this->tenant)->create();
});

test('an admin can create a pool with its service location', function () {
    $this->actingAs($this->admin)
        ->post('/pools', [
            'customer_id' => $this->customer->id, 'name' => 'Lap Pool', 'type' => 'inground',
            'volume_gallons' => 20000, 'sanitizer_type' => 'salt', 'has_heater' => true,
            'address_line1' => '1 Main St', 'city' => 'Orlando', 'state' => 'FL', 'zip' => '32801', 'gate_code' => '1234',
        ])
        ->assertRedirect();

    $pool = Pool::query()->where('name', 'Lap Pool')->first();
    expect($pool)->not->toBeNull();
    expect($pool->has_heater)->toBeTrue();
    expect($pool->serviceLocation?->getAttribute('gate_code'))->toBe('1234');
});

test('an admin can update a pool and its location', function () {
    $pool = Pool::factory()->for($this->tenant)->for($this->customer)->create(['name' => 'Old Name']);

    $this->actingAs($this->admin)
        ->patch("/pools/{$pool->id}", ['name' => 'New Name', 'type' => 'spa', 'sanitizer_type' => 'chlorine', 'city' => 'Tampa'])
        ->assertRedirect();

    expect($pool->fresh()?->name)->toBe('New Name');
    expect($pool->fresh()?->serviceLocation?->getAttribute('city'))->toBe('Tampa');
});

test('a pool cannot be created for a foreign-tenant customer', function () {
    $other = Tenant::factory()->create();
    $foreignCustomer = Customer::factory()->for($other)->create();

    $this->actingAs($this->admin)
        ->post('/pools', ['customer_id' => $foreignCustomer->id, 'name' => 'X'])
        ->assertInvalid('customer_id');
});

test('agents cannot manage pools', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();

    $this->actingAs($agent)->delete("/pools/{$pool->id}")->assertForbidden();
});
