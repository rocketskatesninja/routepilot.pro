<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\ManualCharge;
use App\Models\Pool;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
});

function owingCustomerWithHistory(Tenant $tenant, User $agent): Customer
{
    $customer = Customer::factory()->for($tenant)->create();
    $pool = Pool::factory()->for($tenant)->for($customer)->create();
    $type = ServiceType::factory()->for($tenant)->create(['price' => 50]);
    ServiceSubscription::factory()->for($tenant)->for($pool)->for($type)
        ->create(['status' => 'active', 'assigned_agent_id' => $agent->id]);

    ServiceVisit::factory()->for($tenant)->for($pool)->count(2)
        ->create(['status' => 'completed', 'paid_at' => null, 'completed_at' => now(), 'agent_id' => $agent->id]);

    ManualCharge::create([
        'customer_id' => $customer->id, 'description' => 'Repair', 'amount' => 20,
        'taxable' => false, 'occurred_on' => today(), 'created_by' => $agent->id,
    ]);

    return $customer;
}

test('balancesFor batches unpaid visits + charges per customer', function () {
    $customer = owingCustomerWithHistory($this->tenant, $this->agent);

    // 2 visits x $50 (active sub price) + $20 charge = $120
    expect(app(BillingService::class)->balancesFor([$customer->id])[$customer->id])->toBe(120.0);
});

test('a paid-up customer batches to a zero balance', function () {
    $customer = Customer::factory()->for($this->tenant)->create();

    expect(app(BillingService::class)->balancesFor([$customer->id])[$customer->id])->toBe(0.0);
});

test('the People list carries balance + last visit on customer rows', function () {
    owingCustomerWithHistory($this->tenant, $this->agent);

    $this->actingAs($this->admin)
        ->get('/people?type=customers')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/Index')
            ->where('people.data.0.person_type', 'customer')
            ->where('people.data.0.balance', 120)
            ->where('people.data.0.last_visit', today()->toDateString()));
});
