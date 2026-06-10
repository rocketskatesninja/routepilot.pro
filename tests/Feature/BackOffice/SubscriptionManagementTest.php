<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $this->type = ServiceType::factory()->for($this->tenant)->create();
});

test('an admin can add a service plan to a pool', function () {
    $this->actingAs($this->admin)
        ->post('/subscriptions', [
            'pool_id' => $this->pool->id, 'service_type_id' => $this->type->id,
            'assigned_agent_id' => $this->agent->id, 'frequency' => 'weekly', 'preferred_day' => 'tuesday',
        ])
        ->assertRedirect();

    $sub = ServiceSubscription::query()->where('pool_id', $this->pool->id)->first();
    expect($sub?->status)->toBe('active');
    expect($sub?->getAttribute('assigned_agent_id'))->toBe($this->agent->id);
});

test('an admin can pause a subscription', function () {
    $sub = ServiceSubscription::factory()->for($this->tenant)->for($this->pool)->for($this->type)->create(['status' => 'active']);

    $this->actingAs($this->admin)
        ->patch("/subscriptions/{$sub->id}", ['service_type_id' => $this->type->id, 'frequency' => 'weekly', 'status' => 'paused'])
        ->assertRedirect();

    expect($sub->fresh()?->status)->toBe('paused');
});

test('an admin can remove a subscription', function () {
    $sub = ServiceSubscription::factory()->for($this->tenant)->for($this->pool)->for($this->type)->create();

    $this->actingAs($this->admin)->delete("/subscriptions/{$sub->id}")->assertRedirect();

    $this->assertModelMissing($sub);
});

test('a subscription cannot reference a foreign-tenant pool', function () {
    $other = Tenant::factory()->create();
    $foreignPool = Pool::factory()->for($other)->for(Customer::factory()->for($other))->create();

    $this->actingAs($this->admin)
        ->post('/subscriptions', ['pool_id' => $foreignPool->id, 'service_type_id' => $this->type->id, 'frequency' => 'weekly'])
        ->assertInvalid('pool_id');
});

test('agents cannot manage subscriptions', function () {
    $this->actingAs($this->agent)->post('/subscriptions', ['pool_id' => $this->pool->id])->assertForbidden();
});
