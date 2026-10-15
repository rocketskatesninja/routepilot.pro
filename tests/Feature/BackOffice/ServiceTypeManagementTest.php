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
            'name' => 'Weekly Service', 'frequency' => 'weekly',
            'estimated_duration_minutes' => 30, 'price' => 50,
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

test('a new service opens straight away, saved with exactly the four visit-section switches', function () {
    $response = $this->actingAs($this->admin)->post('/services', [
        'name' => 'Filter clean', 'description' => 'We clean your filter.', 'frequency' => 'one_time',
        'estimated_duration_minutes' => 60, 'price' => 95,
        'field_modules' => ['chemistry' => false, 'treatments' => false],
    ]);

    $type = ServiceType::query()->where('name', 'Filter clean')->firstOrFail();
    $response->assertRedirect("/services?selected={$type->id}");
    expect($type->field_modules)->toBe(['tasks' => true, 'chemistry' => false, 'treatments' => false, 'photos' => true])
        ->and($type->requestable)->toBeTrue()
        ->and($type->offerLabel())->toBe('$95.00 one-time job');
});

test('unknown visit sections and old ways of selling are refused', function () {
    $this->actingAs($this->admin)->post('/services', [
        'name' => 'X', 'frequency' => 'seasonal', 'estimated_duration_minutes' => 30, 'price' => 10,
        'field_modules' => ['tasks' => true, 'teleport' => true],
    ])->assertInvalid(['frequency', 'field_modules']);
});
