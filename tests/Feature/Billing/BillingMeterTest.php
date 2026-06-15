<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Pool;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingMeter;

beforeEach(function () {
    $this->meter = app(BillingMeter::class);
});

test('usage within the allowance carries no overage', function () {
    $p = $this->meter->price(50, 2);

    expect($p['pools']['over'])->toBe(0)
        ->and($p['agents']['over'])->toBe(0)
        ->and($p['overage_total'])->toEqualWithDelta(0.0, 0.001)
        ->and($p['estimated_total'])->toEqualWithDelta(34.99, 0.001);
});

test('pools and agents over the allowance are metered as overage', function () {
    $p = $this->meter->price(53, 4); // 3 pools over @ $0.50, 2 agents over @ $10

    expect($p['pools']['over'])->toBe(3)
        ->and($p['pools']['overage'])->toEqualWithDelta(1.50, 0.001)
        ->and($p['agents']['over'])->toBe(2)
        ->and($p['agents']['overage'])->toEqualWithDelta(20.00, 0.001)
        ->and($p['overage_total'])->toEqualWithDelta(21.50, 0.001)
        ->and($p['estimated_total'])->toEqualWithDelta(56.49, 0.001);
});

test('under-allowance usage is not credited', function () {
    $p = $this->meter->price(10, 1);

    expect($p['pools']['over'])->toBe(0)
        ->and($p['estimated_total'])->toEqualWithDelta(34.99, 0.001);
});

test('the meter counts non-deleted pools and active agents only', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $tenant->id);
    $customer = Customer::factory()->for($tenant)->create();

    Pool::factory()->for($tenant)->for($customer)->count(4)->create();
    Pool::factory()->for($tenant)->for($customer)->create()->delete();      // soft-deleted → not billable

    User::factory()->agent()->for($tenant)->count(2)->create(['is_active' => true]);
    User::factory()->agent()->for($tenant)->create(['is_active' => false]); // inactive agent → not billable
    User::factory()->for($tenant)->create();                                // tenant_admin → not an agent

    $usage = $this->meter->for($tenant);

    expect($usage['pools']['used'])->toBe(4)
        ->and($usage['agents']['used'])->toBe(2);
});
