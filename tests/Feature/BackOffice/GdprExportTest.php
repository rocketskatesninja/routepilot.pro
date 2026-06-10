<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create(['first_name' => 'Dana']);
    Pool::factory()->for($this->tenant)->for($this->customer)->create();
});

test('an admin exports a customer\'s data, audited', function () {
    $this->actingAs($this->admin)
        ->get("/customers/{$this->customer->id}/export")
        ->assertOk()
        ->assertJsonPath('customer.first_name', 'Dana')
        ->assertJsonStructure(['customer', 'pools', 'charges', 'invoices']);

    expect(AuditLog::query()->where('action', 'customer.exported')->where('model_id', $this->customer->id)->exists())->toBeTrue();
});

test('deleting a customer is audited', function () {
    $this->actingAs($this->admin)->delete("/customers/{$this->customer->id}")->assertRedirect();

    expect(AuditLog::query()->where('action', 'customer.deleted')->where('model_id', $this->customer->id)->exists())->toBeTrue();
});

test('agents cannot export customer data', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)->get("/customers/{$this->customer->id}/export")->assertForbidden();
});
