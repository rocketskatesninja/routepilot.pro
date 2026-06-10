<?php

use App\Models\Customer;
use App\Models\Tenant;

/**
 * The global TenantScope (via BelongsToTenant) must make one tenant's rows
 * invisible to another once tenant context is bound.
 */
test('queries are scoped to the bound tenant', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    Customer::factory()->for($a)->count(2)->create();
    Customer::factory()->for($b)->count(3)->create();

    app()->instance('tenant_id', $a->id);
    expect(Customer::count())->toBe(2);

    app()->instance('tenant_id', $b->id);
    expect(Customer::count())->toBe(3);
});

test('new tenant-owned records inherit the bound tenant_id', function () {
    $a = Tenant::factory()->create();
    app()->instance('tenant_id', $a->id);

    $customer = Customer::create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);

    expect($customer->tenant_id)->toBe($a->id);
});

test('a foreign tenant record cannot be fetched under the wrong context', function () {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();

    $foreign = Customer::factory()->for($b)->create();

    app()->instance('tenant_id', $a->id);

    expect(Customer::find($foreign->id))->toBeNull();
});
