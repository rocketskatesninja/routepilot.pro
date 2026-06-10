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
});

test('an admin can create a service type with tasks + module flags', function () {
    $this->actingAs($this->admin)
        ->post('/services', [
            'name' => 'Weekly Service', 'category' => 'routine', 'frequency' => 'weekly',
            'estimated_duration_minutes' => 30, 'price' => 50, 'chemicals_included' => true,
            'tasks' => ['Skim', '', 'Brush walls'],
            'field_modules' => ['tasks' => true, 'chemistry' => true, 'treatments' => false, 'photos' => true],
            'is_active' => true,
        ])
        ->assertRedirect();

    $type = ServiceType::query()->where('name', 'Weekly Service')->first();
    expect($type)->not->toBeNull();
    expect($type->tasks)->toBe(['Skim', 'Brush walls']); // blank line dropped
    expect($type->field_modules['treatments'])->toBeFalse();
});

test('an admin can update a service type', function () {
    $type = ServiceType::factory()->for($this->tenant)->create(['name' => 'Old']);

    $this->actingAs($this->admin)
        ->patch("/services/{$type->id}", [
            'name' => 'Renamed', 'frequency' => 'monthly', 'estimated_duration_minutes' => 45, 'price' => 75,
        ])
        ->assertRedirect();

    expect($type->fresh()?->name)->toBe('Renamed');
});

test('an unused service type can be deleted', function () {
    $type = ServiceType::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)->delete("/services/{$type->id}")->assertRedirect();

    $this->assertDatabaseMissing('service_types', ['id' => $type->id]);
});

test('a service type in use cannot be deleted', function () {
    $type = ServiceType::factory()->for($this->tenant)->create();
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    ServiceSubscription::factory()->for($this->tenant)->for($pool)->for($type)->create(['status' => 'active']);

    $this->actingAs($this->admin)->delete("/services/{$type->id}")->assertRedirect();

    $this->assertDatabaseHas('service_types', ['id' => $type->id]); // still there
});

test('agents cannot manage service types', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)->post('/services', ['name' => 'X'])->assertForbidden();
});
