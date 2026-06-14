<?php

declare(strict_types=1);

use App\Actions\GenerateInvoice;
use App\Models\Customer;
use App\Models\ManualCharge;
use App\Models\Payment;
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

/** A customer with one unpaid $50 visit. */
function customerWithUnpaidVisit(Tenant $tenant, User $agent): Customer
{
    $customer = Customer::factory()->for($tenant)->create();
    $pool = Pool::factory()->for($tenant)->for($customer)->create();
    $type = ServiceType::factory()->for($tenant)->create(['price' => 50]);
    ServiceSubscription::factory()->for($tenant)->for($pool)->for($type)->create(['assigned_agent_id' => $agent->id, 'status' => 'active']);
    ServiceVisit::factory()->for($tenant)->for($pool)->create(['agent_id' => $agent->id, 'status' => 'completed', 'completed_at' => now(), 'paid_at' => null]);

    return $customer;
}

test('an admin can add a manual charge', function () {
    $customer = Customer::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)
        ->post('/balances/charges', ['customer_id' => $customer->id, 'description' => 'Filter cartridge', 'amount' => 89, 'taxable' => true])
        ->assertRedirect();

    expect(ManualCharge::query()->where('customer_id', $customer->id)->where('description', 'Filter cartridge')->exists())->toBeTrue();
});

test('recording a payment settles visits + charges and writes a Payment', function () {
    $customer = customerWithUnpaidVisit($this->tenant, $this->agent);
    ManualCharge::create(['customer_id' => $customer->id, 'description' => 'Repair', 'amount' => 89, 'created_by' => $this->admin->id, 'occurred_on' => now()]);

    expect(app(BillingService::class)->outstandingForCustomer($customer))->toBe(139.0);

    $this->actingAs($this->admin)->post("/balances/{$customer->id}/pay", ['method' => 'cash'])->assertRedirect();

    expect(app(BillingService::class)->outstandingForCustomer($customer->fresh()))->toBe(0.0);
    expect((float) Payment::query()->where('customer_id', $customer->id)->value('amount'))->toBe(139.0);
});

test('a manual charge cannot target a foreign-tenant customer', function () {
    $other = Tenant::factory()->create();
    $foreign = Customer::factory()->for($other)->create();

    $this->actingAs($this->admin)
        ->post('/balances/charges', ['customer_id' => $foreign->id, 'description' => 'X', 'amount' => 10])
        ->assertInvalid('customer_id');
});

test('agents cannot manage balances', function () {
    $this->actingAs($this->agent)->post('/balances/charges', ['customer_id' => 1, 'description' => 'X', 'amount' => 10])->assertForbidden();
});

test('the invoices view lists invoices and opens one in the detail pane', function () {
    $customer = customerWithUnpaidVisit($this->tenant, $this->agent);
    $invoice = app(GenerateInvoice::class)->handle($customer);

    $this->actingAs($this->admin)
        ->get('/balances?view=invoices')
        ->assertInertia(fn (Assert $page) => $page
            ->where('view', 'invoices')
            ->where('counts.invoices', 1)
            ->where('invoices.data.0.number', $invoice?->number)
        );

    $this->actingAs($this->admin)
        ->get("/balances?view=invoices&selected={$invoice?->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.kind', 'invoice')
            ->where('selected.id', $invoice?->id)
            ->has('selected.line_items', 1)
        );
});

test('marking an invoice paid settles it and its source visit', function () {
    $customer = customerWithUnpaidVisit($this->tenant, $this->agent);
    $invoice = app(GenerateInvoice::class)->handle($customer);

    $this->actingAs($this->admin)
        ->post("/invoices/{$invoice?->id}/mark-paid", ['method' => 'check'])
        ->assertRedirect();

    expect($invoice?->fresh()?->status)->toBe('paid')
        ->and($invoice?->fresh()?->balance())->toBe(0.0)
        // The source visit is settled, so the customer no longer owes.
        ->and(app(BillingService::class)->outstandingForCustomer($customer->fresh()))->toBe(0.0);

    $this->assertDatabaseHas('payments', ['invoice_id' => $invoice?->id, 'method' => 'check']);
});

test('agents cannot mark invoices paid', function () {
    $customer = customerWithUnpaidVisit($this->tenant, $this->agent);
    $invoice = app(GenerateInvoice::class)->handle($customer);

    $this->actingAs($this->agent)
        ->post("/invoices/{$invoice?->id}/mark-paid", ['method' => 'cash'])
        ->assertForbidden();
});

test('a foreign-tenant invoice cannot be marked paid', function () {
    $other = Tenant::factory()->create();
    app()->instance('tenant_id', $other->id);
    $foreign = app(GenerateInvoice::class)->handle(customerWithUnpaidVisit($other, User::factory()->agent()->for($other)->create()));
    app()->instance('tenant_id', $this->tenant->id);

    $this->actingAs($this->admin)
        ->post("/invoices/{$foreign?->id}/mark-paid", ['method' => 'cash'])
        ->assertNotFound();
});
