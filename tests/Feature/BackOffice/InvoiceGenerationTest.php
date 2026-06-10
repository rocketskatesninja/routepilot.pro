<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ManualCharge;
use App\Models\Pool;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();

    // A customer with one unpaid $50 visit + a $100 taxable manual charge.
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($this->customer)->create(['name' => 'Pool A']);
    $type = ServiceType::factory()->for($this->tenant)->create(['price' => 50]);
    ServiceSubscription::factory()->for($this->tenant)->for($pool)->for($type)->create(['assigned_agent_id' => $this->agent->id, 'status' => 'active']);
    ServiceVisit::factory()->for($this->tenant)->for($pool)->create(['agent_id' => $this->agent->id, 'status' => 'completed', 'completed_at' => now(), 'paid_at' => null]);
    ManualCharge::create(['customer_id' => $this->customer->id, 'description' => 'Filter', 'amount' => 100, 'taxable' => true, 'occurred_on' => now(), 'created_by' => $this->admin->id]);
});

test('an admin generates an invoice with line items + tax', function () {
    $this->tenant->forceFill(['tax_rate' => 0.10])->save();

    $this->actingAs($this->admin)->post("/balances/{$this->customer->id}/invoice")->assertRedirect();

    $invoice = Invoice::query()->where('customer_id', $this->customer->id)->first();
    expect((float) $invoice?->subtotal)->toBe(150.0);
    expect((float) $invoice?->tax)->toBe(10.0);   // 10% of the taxable $100 charge only
    expect((float) $invoice?->total)->toBe(160.0);
    expect($invoice?->lineItems()->count())->toBe(2);
});

test('generating twice does not double-bill the same items', function () {
    $this->actingAs($this->admin)->post("/balances/{$this->customer->id}/invoice")->assertRedirect();
    $this->actingAs($this->admin)->post("/balances/{$this->customer->id}/invoice")->assertRedirect();

    expect(Invoice::query()->where('customer_id', $this->customer->id)->count())->toBe(1);
});

test('recording a payment settles the open invoice', function () {
    $this->actingAs($this->admin)->post("/balances/{$this->customer->id}/invoice")->assertRedirect();
    $this->actingAs($this->admin)->post("/balances/{$this->customer->id}/pay", ['method' => 'cash'])->assertRedirect();

    $invoice = Invoice::query()->where('customer_id', $this->customer->id)->first();
    expect($invoice?->status)->toBe('paid');
    expect((float) $invoice?->amount_paid)->toBe((float) $invoice?->total);
    expect(app(BillingService::class)->outstandingForCustomer($this->customer->fresh()))->toBe(0.0);
});

test('a customer with nothing outstanding produces no invoice', function () {
    $empty = Customer::factory()->for($this->tenant)->create();

    $this->actingAs($this->admin)->post("/balances/{$empty->id}/invoice")->assertRedirect();

    expect(Invoice::query()->where('customer_id', $empty->id)->exists())->toBeFalse();
});

test('agents cannot generate invoices', function () {
    $this->actingAs($this->agent)->post("/balances/{$this->customer->id}/invoice")->assertForbidden();
});
