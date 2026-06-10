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

test('an admin can create a customer with an optional first pool', function () {
    $this->actingAs($this->admin)
        ->post('/customers', [
            'first_name' => 'New', 'last_name' => 'Customer', 'email' => 'new@example.test',
            'city' => 'Orlando', 'state' => 'FL', 'zip' => '32801', 'bill_chemicals' => true,
            'pool_name' => 'Backyard', 'pool_type' => 'inground', 'pool_volume' => 15000, 'pool_sanitizer' => 'salt',
        ])
        ->assertRedirect();

    $customer = Customer::query()->where('email', 'new@example.test')->first();
    expect($customer)->not->toBeNull();
    expect($customer->tenant_id)->toBe($this->tenant->id);

    $pool = Pool::query()->where('customer_id', $customer->id)->first();
    expect($pool?->name)->toBe('Backyard');
    expect($pool?->serviceLocation)->not->toBeNull();
});

test('an admin can update a customer', function () {
    $customer = Customer::factory()->for($this->tenant)->create(['first_name' => 'Old']);

    $this->actingAs($this->admin)
        ->patch("/customers/{$customer->id}", ['first_name' => 'Renamed', 'bill_chemicals' => false])
        ->assertRedirect();

    expect($customer->fresh()?->first_name)->toBe('Renamed');
});

test('an admin can soft-delete a customer', function () {
    $customer = Customer::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)->delete("/customers/{$customer->id}")->assertRedirect();

    $this->assertSoftDeleted('customers', ['id' => $customer->id]);
});

test('a foreign-tenant customer cannot be edited (404)', function () {
    $other = Tenant::factory()->create();
    $foreign = Customer::factory()->for($other)->create();

    $this->actingAs($this->admin)->patch("/customers/{$foreign->id}", ['first_name' => 'Hack'])->assertNotFound();
});

test('agents cannot manage customers', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)->post('/customers', ['first_name' => 'X'])->assertForbidden();
});
