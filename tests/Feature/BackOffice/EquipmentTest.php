<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\EquipmentServiceLog;
use App\Models\ManualCharge;
use App\Models\Pool;
use App\Models\PoolEquipment;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
});

test('an admin adds equipment to a pool', function () {
    $this->actingAs($this->admin)
        ->post('/equipment', ['pool_id' => $this->pool->id, 'type' => 'pump', 'make' => 'Pentair', 'model' => 'IntelliFlo'])
        ->assertRedirect();

    expect(PoolEquipment::query()->where('pool_id', $this->pool->id)->where('type', 'pump')->exists())->toBeTrue();
});

test('logging a billable repair creates a manual charge', function () {
    $equipment = PoolEquipment::create(['pool_id' => $this->pool->id, 'type' => 'heater']);

    $this->actingAs($this->admin)
        ->post("/equipment/{$equipment->id}/service", ['description' => 'Replaced igniter', 'cost' => 150, 'bill' => true])
        ->assertRedirect();

    expect(EquipmentServiceLog::query()->where('pool_equipment_id', $equipment->id)->exists())->toBeTrue();
    expect((float) ManualCharge::query()->where('customer_id', $this->customer->id)->value('amount'))->toBe(150.0);
});

test('an unbilled repair does not charge the customer', function () {
    $equipment = PoolEquipment::create(['pool_id' => $this->pool->id, 'type' => 'filter']);

    $this->actingAs($this->admin)
        ->post("/equipment/{$equipment->id}/service", ['description' => 'Cleaned cartridge', 'cost' => 0, 'bill' => false])
        ->assertRedirect();

    expect(ManualCharge::query()->where('customer_id', $this->customer->id)->exists())->toBeFalse();
});

test('an admin removes equipment', function () {
    $equipment = PoolEquipment::create(['pool_id' => $this->pool->id, 'type' => 'pump']);

    $this->actingAs($this->admin)->delete("/equipment/{$equipment->id}")->assertRedirect();

    expect(PoolEquipment::query()->whereKey($equipment->id)->exists())->toBeFalse();
});

test('foreign-tenant equipment is not found', function () {
    $other = Tenant::factory()->create();
    $foreignPool = Pool::factory()->for($other)->for(Customer::factory()->for($other))->create();
    $foreign = new PoolEquipment(['pool_id' => $foreignPool->id, 'type' => 'pump']);
    $foreign->forceFill(['tenant_id' => $other->id])->save();

    $this->actingAs($this->admin)->patch("/equipment/{$foreign->id}", ['type' => 'filter'])->assertNotFound();
});

test('agents cannot manage equipment', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)->post('/equipment', ['pool_id' => $this->pool->id, 'type' => 'pump'])->assertForbidden();
});
