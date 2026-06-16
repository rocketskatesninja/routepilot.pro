<?php

declare(strict_types=1);

use App\Models\AuditLog;
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

function auditCount(string $action): int
{
    return AuditLog::query()->where('action', $action)->count();
}

test('deleting an agent is audited', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($this->admin)->delete("/agents/{$agent->id}")->assertRedirect();

    expect(auditCount('agent.deleted'))->toBe(1);
});

test('changing an agent privilege flag is audited, a profile edit is not', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();
    $email = (string) $agent->getAttribute('email');

    // Profile-only edit (privilege flags unchanged) → no audit row.
    $this->actingAs($this->admin)
        ->patch("/agents/{$agent->id}", ['first_name' => 'Renamed', 'email' => $email])
        ->assertRedirect();
    expect(auditCount('agent.privilege_changed'))->toBe(0);

    // Deactivation → audited.
    $this->actingAs($this->admin)
        ->patch("/agents/{$agent->id}", ['first_name' => 'Renamed', 'email' => $email, 'is_active' => false])
        ->assertRedirect();
    expect(auditCount('agent.privilege_changed'))->toBe(1);
});

test('deleting a pool is audited', function () {
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();

    $this->actingAs($this->admin)->delete("/pools/{$pool->id}")->assertRedirect();

    expect(auditCount('pool.deleted'))->toBe(1);
});

test('deleting a subscription is audited', function () {
    $customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    $type = ServiceType::factory()->for($this->tenant)->create();
    $sub = ServiceSubscription::factory()->for($this->tenant)->for($pool)->for($type)->create();

    $this->actingAs($this->admin)->delete("/subscriptions/{$sub->id}")->assertRedirect();

    expect(auditCount('subscription.deleted'))->toBe(1);
});

test('adding a manual charge is audited', function () {
    $customer = Customer::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->post('/balances/charges', ['customer_id' => $customer->id, 'description' => 'Filter swap', 'amount' => 75])
        ->assertRedirect();

    expect(auditCount('charge.created'))->toBe(1);
});

test('granting portal access is audited', function () {
    $customer = Customer::factory()->for($this->tenant)->create(['email' => 'grant@portal.test']);

    $this->actingAs($this->admin)
        ->post("/customers/{$customer->id}/portal", ['password' => 'password123'])
        ->assertRedirect();

    expect(auditCount('customer.portal_granted'))->toBe(1);
});
