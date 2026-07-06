<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Pool;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
});

/** A customer with one unpaid completed visit priced via an active $50 subscription. */
function owingCustomer(Tenant $tenant, User $agent): Customer
{
    $customer = Customer::factory()->for($tenant)->create(['first_name' => 'Owes', 'last_name' => 'Money']);
    $pool = Pool::factory()->for($tenant)->for($customer)->create();
    $type = ServiceType::factory()->for($tenant)->create(['price' => 50]);
    ServiceSubscription::factory()->for($tenant)->for($pool)->for($type)->create(['assigned_agent_id' => $agent->id, 'status' => 'active']);
    ServiceVisit::factory()->for($tenant)->for($pool)->create([
        'agent_id' => $agent->id, 'status' => 'completed', 'completed_at' => now(), 'paid_at' => null,
    ]);

    return $customer;
}

test('lists only customers with an outstanding balance', function () {
    owingCustomer($this->tenant, $this->agent);
    Customer::factory()->for($this->tenant)->create(); // no visits -> paid up

    $this->actingAs($this->admin)
        ->get('/balances')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('balances/Index')
            ->has('balances.data', 1)
            ->where('balances.data.0.name', 'Owes Money')
            ->where('balances.data.0.balance', 50)
            ->where('total', 50)
        );
});

test('the owing list surfaces each customer autopay status + saved card', function () {
    $customer = owingCustomer($this->tenant, $this->agent);
    $pm = PaymentMethod::create([
        'customer_id' => $customer->id, 'stripe_payment_method_id' => 'pm_1',
        'brand' => 'visa', 'last4' => '4242', 'is_default' => true,
    ]);
    $customer->forceFill(['autopay_enabled' => true, 'default_payment_method_id' => $pm->id])->save();

    $this->actingAs($this->admin)
        ->get('/balances')
        ->assertInertia(fn (Assert $page) => $page
            ->where('balances.data.0.autopay', true)
            ->where('balances.data.0.card', 'Visa •••• 4242')
        );
});

test('the drawer breaks the balance down by visit', function () {
    $customer = owingCustomer($this->tenant, $this->agent);

    $this->actingAs($this->admin)
        ->get("/balances?selected={$customer->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.id', $customer->id)
            ->where('selected.total', 50)
            ->has('selected.visits', 1)
            ->where('selected.visits.0.price', 50)
        );
});

test('customers are denied the Balances screen', function () {
    $portalUser = User::factory()->customer()->for($this->tenant)->create();

    $this->actingAs($portalUser)->get('/balances')->assertForbidden();
});
