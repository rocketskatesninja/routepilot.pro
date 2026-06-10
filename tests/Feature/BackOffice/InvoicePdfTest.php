<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
});

function makeInvoice(Customer $customer): Invoice
{
    $invoice = Invoice::create([
        'customer_id' => $customer->id, 'number' => 'INV-00001', 'status' => 'sent',
        'subtotal' => 50, 'tax' => 0, 'total' => 50, 'amount_paid' => 0,
        'issued_at' => now(), 'due_at' => now()->addDays(30),
    ]);
    $invoice->lineItems()->create(['type' => 'service', 'description' => 'Pool service', 'quantity' => 1, 'unit_price' => 50, 'amount' => 50, 'taxable' => false]);

    return $invoice;
}

test('an admin downloads an invoice PDF', function () {
    $invoice = makeInvoice($this->customer);

    $this->actingAs($this->admin)
        ->get("/invoices/{$invoice->id}/pdf")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('a customer downloads their own invoice', function () {
    $user = User::factory()->customer()->for($this->tenant)->create();
    $this->customer->forceFill(['user_id' => $user->id])->save();
    $invoice = makeInvoice($this->customer);

    $this->actingAs($user)->get("/invoices/{$invoice->id}/pdf")->assertOk();
});

test('a customer cannot download another customer\'s invoice', function () {
    $user = User::factory()->customer()->for($this->tenant)->create();
    $this->customer->forceFill(['user_id' => $user->id])->save();

    $otherInvoice = makeInvoice(Customer::factory()->for($this->tenant)->create());

    $this->actingAs($user)->get("/invoices/{$otherInvoice->id}/pdf")->assertForbidden();
});
