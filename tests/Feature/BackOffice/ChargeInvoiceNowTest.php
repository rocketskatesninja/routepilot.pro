<?php

declare(strict_types=1);

use App\Mail\InvoiceMail;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Pool;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Mail;

/*
 * Adding a charge can put it on an invoice and email it right away ("Invoice
 * and email it now"), the same as the "Invoice now" button.
 */

beforeEach(function () {
    Mail::fake();
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create(['last_name' => 'Rodriguez', 'email' => 'diane@example.test']);
});

test('left unticked, the charge just goes on the balance', function () {
    $this->actingAs($this->admin)
        ->post('/balances/charges', ['customer_id' => $this->customer->id, 'description' => 'Filter', 'amount' => 45])
        ->assertSessionHas('success', 'Charge added.');

    expect(Charge::query()->sole()->invoice_id)->toBeNull()
        ->and(Invoice::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

test('ticked, it goes on an invoice with anything else unbilled, and the invoice is emailed', function () {
    manualCharge($this->customer, 20, 'Earlier extra');

    $this->actingAs($this->admin)
        ->post('/balances/charges', ['customer_id' => $this->customer->id, 'description' => 'Filter', 'amount' => 45, 'invoice_now' => true])
        ->assertSessionHas('success', fn (string $m) => str_starts_with($m, 'Charge added. Invoice RODRIGUEZ-') && str_ends_with($m, 'emailed to diane@example.test.'));

    $invoice = Invoice::query()->sole();
    expect(Charge::query()->pluck('invoice_id')->unique()->all())->toBe([$invoice->id])
        ->and((float) $invoice->total)->toBe(65.0)
        ->and($invoice->status)->toBe('sent');
    Mail::assertQueued(InvoiceMail::class, fn (InvoiceMail $m) => $m->hasTo('diane@example.test'));
});

test('with no email address on file, the invoice waits as a draft', function () {
    $this->customer->update(['email' => null]);

    $this->actingAs($this->admin)
        ->post('/balances/charges', ['customer_id' => $this->customer->id, 'description' => 'Filter', 'amount' => 45, 'invoice_now' => true])
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'so it’s a draft') || str_contains($m, "so it's a draft"));

    expect(Invoice::query()->sole()->status)->toBe('draft');
    Mail::assertNothingQueued();
});

test('billing a work order can email it too, and goes back to the work order', function () {
    $pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $order = WorkOrder::factory()->create(['pool_id' => $pool->id, 'status' => 'done', 'price' => 120]);

    $this->actingAs($this->admin)
        ->post('/balances/charges', ['customer_id' => $this->customer->id, 'description' => 'Pump seal', 'amount' => 120, 'work_order_id' => $order->id, 'invoice_now' => true])
        ->assertRedirect(route('work-orders.index', ['status' => 'done', 'selected' => $order->id]));

    expect(Invoice::query()->sole()->status)->toBe('sent');
    Mail::assertQueued(InvoiceMail::class);
});
