<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create(['first_name' => 'Dana', 'notes' => 'Dog in the yard', 'admin_notes' => 'Pays late']);
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create(['notes' => 'Heater is finicky']);
});

test('an admin exports a customer\'s data, audited', function () {
    $this->actingAs($this->admin)
        ->get("/customers/{$this->customer->id}/export")
        ->assertOk()
        ->assertJsonPath('customer.first_name', 'Dana')
        ->assertJsonStructure(['customer', 'pools', 'charges', 'invoices']);

    expect(AuditLog::query()->where('action', 'customer.exported')->where('model_id', $this->customer->id)->exists())->toBeTrue();
});

test('the export includes the notes kept about them, office-only ones and work orders too', function () {
    $visit = ServiceVisit::factory()->for($this->tenant)->for($this->pool)->create([
        'agent_id' => User::factory()->agent()->for($this->tenant)->create()->id,
        'status' => 'completed', 'completed_at' => now(), 'visited_at' => now(), 'notes' => 'Water looks great.', 'office_notes' => 'Pump seal is leaking',
    ]);
    $order = WorkOrder::factory()->for($this->tenant)->create(['pool_id' => $this->pool->id, 'service_visit_id' => $visit->id, 'title' => 'Replace the pump seal', 'details' => 'Before Friday']);
    $order->comments()->create(['body' => 'Part ordered']);
    WorkOrder::factory()->for($this->tenant)->create(['title' => 'Order chlorine tabs']); // not about Dana

    $this->actingAs($this->admin)->get("/customers/{$this->customer->id}/export")
        ->assertOk()
        ->assertJsonPath('customer.notes', 'Dog in the yard')
        ->assertJsonPath('customer.office_notes', 'Pays late')
        ->assertJsonPath('pools.0.notes', 'Heater is finicky')
        ->assertJsonPath('pools.0.visits.0.notes', 'Water looks great.')
        ->assertJsonPath('pools.0.visits.0.office_notes', 'Pump seal is leaking')
        ->assertJsonCount(1, 'work_orders')
        ->assertJsonPath('work_orders.0.title', 'Replace the pump seal')
        ->assertJsonPath('work_orders.0.details', 'Before Friday')
        ->assertJsonPath('work_orders.0.notes.0.note', 'Part ordered');
});

test('deleting a customer is audited', function () {
    $this->actingAs($this->admin)->delete("/customers/{$this->customer->id}")->assertRedirect();

    expect(AuditLog::query()->where('action', 'customer.deleted')->where('model_id', $this->customer->id)->exists())->toBeTrue();
});

test('agents cannot export customer data', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)->get("/customers/{$this->customer->id}/export")->assertForbidden();
});
