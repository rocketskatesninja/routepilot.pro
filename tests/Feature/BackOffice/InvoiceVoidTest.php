<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pool;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();

    // A customer owing $150 = one unpaid $50 visit + a $100 manual charge.
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $type = ServiceType::factory()->for($this->tenant)->create(['price' => 50]);
    chargedVisit(ServiceSubscription::factory()->for($this->tenant)->for($pool)->for($type)->create(['assigned_agent_id' => $this->agent->id, 'status' => 'active']));
    manualCharge($this->customer, 100, 'Filter');
});

test('voiding an invoice writes off its charges — they leave the customer balance, with no payment', function () {
    $this->actingAs($this->admin)->post("/balances/{$this->customer->id}/invoice")->assertRedirect();
    $invoice = Invoice::query()->where('customer_id', $this->customer->id)->firstOrFail();
    expect(app(BillingService::class)->outstandingForCustomer($this->customer->fresh()))->toBe(150.0);

    $this->actingAs($this->admin)->post("/invoices/{$invoice->id}/void")->assertRedirect();

    expect($invoice->fresh()->status)->toBe('void')
        ->and(app(BillingService::class)->outstandingForCustomer($this->customer->fresh()))->toBe(0.0)
        ->and(Payment::query()->where('invoice_id', $invoice->id)->exists())->toBeFalse()
        ->and(AuditLog::where('action', 'invoice.voided')->where('model_id', $invoice->id)->exists())->toBeTrue();
});

test('a paid invoice cannot be voided', function () {
    $this->actingAs($this->admin)->post("/balances/{$this->customer->id}/invoice")->assertRedirect();
    $invoice = Invoice::query()->where('customer_id', $this->customer->id)->firstOrFail();
    $this->actingAs($this->admin)->post("/balances/{$this->customer->id}/pay", ['method' => 'cash'])->assertRedirect();
    expect($invoice->fresh()->status)->toBe('paid');

    $this->actingAs($this->admin)->post("/invoices/{$invoice->id}/void")->assertSessionHas('error');
    expect($invoice->fresh()->status)->toBe('paid');
});

test('agents cannot void invoices', function () {
    $this->actingAs($this->admin)->post("/balances/{$this->customer->id}/invoice")->assertRedirect();
    $invoice = Invoice::query()->where('customer_id', $this->customer->id)->firstOrFail();

    $this->actingAs($this->agent)->post("/invoices/{$invoice->id}/void")->assertForbidden();
    expect($invoice->fresh()->status)->not->toBe('void');
});
