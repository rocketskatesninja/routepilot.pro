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
        ->and($p['overage_total'])->toEqualWithDelta(0.0, 0.001)
        ->and($p['estimated_total'])->toEqualWithDelta(34.99, 0.001);
});

test('pools over the allowance are metered as overage', function () {
    $p = $this->meter->price(53, 4); // 3 pools over @ $0.75

    expect($p['pools']['over'])->toBe(3)
        ->and($p['pools']['overage'])->toEqualWithDelta(2.25, 0.001)
        ->and($p['overage_total'])->toEqualWithDelta(2.25, 0.001)
        ->and($p['estimated_total'])->toEqualWithDelta(37.24, 0.001);
});

test('techs are unlimited: adding them never changes the price', function () {
    $few = $this->meter->price(53, 2);
    $many = $this->meter->price(53, 40);

    expect($many['estimated_total'])->toBe($few['estimated_total'])
        ->and($many['agents'])->toBe(40); // still counted, to show company size
});

test('under-allowance usage is not credited', function () {
    $p = $this->meter->price(10, 1);

    expect($p['pools']['over'])->toBe(0)
        ->and($p['estimated_total'])->toEqualWithDelta(34.99, 0.001);
});

test('the meter counts non-deleted pools, and active agents for information', function () {
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
        ->and($usage['agents'])->toBe(2);
});
