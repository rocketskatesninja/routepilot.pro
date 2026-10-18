<?php

declare(strict_types=1);

use App\Actions\GenerateInvoice;
use App\Actions\RecordPayment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Where a customer's money went: the customer's panel lists their payments
 * (and declined autopay tries), and each invoice says what paid it — even a
 * payment made on account, which pays the oldest bills first.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create(['first_name' => 'Sarah']);
    $this->customer = Customer::factory()->for($this->tenant)->create();
});

/** Bill a charge on its own invoice, on the given day. */
function billOn(Customer $customer, string $day, float $amount): Invoice
{
    Carbon::setTestNow($day.' 12:00:00');
    app(BillingService::class)->addCharge($customer, 'manual', 'Service', $amount, false, $day, null);

    return app(GenerateInvoice::class)->handle($customer) ?? throw new RuntimeException('nothing billed');
}

test('a payment on account shows on the invoices it paid, split oldest first', function () {
    $older = billOn($this->customer, '2026-09-01', 100);
    $newer = billOn($this->customer, '2026-10-01', 100);
    app(RecordPayment::class)->handle($this->customer, 'card', null, 'pi_1', 150.0); // paid online, not against an invoice

    $billing = app(BillingService::class);
    $paidOlder = $billing->paidBy($older);
    $paidNewer = $billing->paidBy($newer);

    expect($paidOlder)->toHaveCount(1)
        ->and($paidOlder[0]['applied'])->toBe(100.0)
        ->and($paidNewer[0]['applied'])->toBe(50.0)
        ->and($paidNewer[0]['source']->is($paidOlder[0]['source']))->toBeTrue();
});

test('a payment made against an invoice shows on that invoice, not an older one', function () {
    $older = billOn($this->customer, '2026-09-01', 100);
    $newer = billOn($this->customer, '2026-10-01', 80);
    app(RecordPayment::class)->handle($this->customer, 'check', $this->admin->id, invoice: $newer);

    expect(app(BillingService::class)->paidBy($older))->toBe([])
        ->and(app(BillingService::class)->paidBy($newer)[0]['applied'])->toBe(80.0);
});

test('a credit that paid an invoice shows up as what paid it', function () {
    $invoice = billOn($this->customer, '2026-09-01', 100);
    app(BillingService::class)->addCharge($this->customer, 'credit', 'Goodwill credit', -20, false, '2026-09-05', null);

    $paid = app(BillingService::class)->paidBy($invoice);

    expect($paid)->toHaveCount(1)
        ->and($paid[0]['source']->description)->toBe('Goodwill credit')
        ->and($paid[0]['applied'])->toBe(20.0);
});

test('the invoice panel lists what paid it', function () {
    $invoice = billOn($this->customer, '2026-09-01', 100);
    app(RecordPayment::class)->handle($this->customer, 'cash', $this->admin->id, null, 60.0);

    $this->actingAs($this->admin)->get("/balances?view=invoices&selected={$invoice->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->has('selected.paid_by', 1)
            ->where('selected.paid_by.0.method', 'cash')
            ->where('selected.paid_by.0.by', 'Sarah')
            ->where('selected.paid_by.0.applied', 60)
            ->where('selected.amount_paid', 60));
});

test('the customer panel lists their payments and declined autopay tries, newest first', function () {
    billOn($this->customer, '2026-09-01', 100);
    Carbon::setTestNow('2026-10-02 05:00:00');
    Payment::create(['customer_id' => $this->customer->id, 'amount' => 100, 'status' => 'failed', 'method' => 'card', 'failure_reason' => 'Autopay charge declined']);
    Carbon::setTestNow('2026-10-05 15:00:00');
    app(RecordPayment::class)->handle($this->customer, 'check', $this->admin->id, null, 40.0);

    $this->actingAs($this->admin)->get("/balances?selected={$this->customer->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->has('selected.payments', 2)
            ->where('selected.payments.0.method', 'check')
            ->where('selected.payments.0.by', 'Sarah')
            ->where('selected.payments.0.declined', false)
            ->where('selected.payments.1.declined', true)
            ->where('selected.payments.1.on', '2026-10-02'));
});

test('a paid-up customer\'s panel still shows their payment history', function () {
    billOn($this->customer, '2026-09-01', 100);
    app(RecordPayment::class)->handle($this->customer, 'cash', $this->admin->id);

    $this->actingAs($this->admin)->get("/balances?selected={$this->customer->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.total', 0)
            ->has('selected.payments', 1));
});

// --- partial payments ------------------------------------------------------------

test('the office can record part of what a customer owes', function () {
    billOn($this->customer, '2026-09-01', 100);

    $this->actingAs($this->admin)->post("/balances/{$this->customer->id}/pay", ['method' => 'check', 'amount' => '40'])
        ->assertSessionHas('success', fn (string $m): bool => str_contains($m, '$60.00 is still owed'));

    expect((float) Payment::query()->sole()->amount)->toBe(40.0)
        ->and(app(BillingService::class)->outstandingForCustomer($this->customer))->toBe(60.0);
});

test('part of an invoice can be paid, and it stays open for the rest', function () {
    $invoice = billOn($this->customer, '2026-09-01', 100);

    $this->actingAs($this->admin)->post("/invoices/{$invoice->id}/mark-paid", ['method' => 'cash', 'amount' => '25.50'])
        ->assertSessionHas('success');

    expect((float) $invoice->fresh()?->amount_paid)->toBe(25.5)
        ->and($invoice->fresh()?->balance())->toBe(74.5)
        ->and($invoice->fresh()?->status)->not->toBe('paid')
        ->and(Payment::query()->sole()->getAttribute('invoice_id'))->toBe($invoice->id);
});

test('a payment can\'t be more than is owed', function () {
    $invoice = billOn($this->customer, '2026-09-01', 100);

    $this->actingAs($this->admin)->post("/balances/{$this->customer->id}/pay", ['method' => 'cash', 'amount' => '100.01'])->assertInvalid('amount');
    $this->actingAs($this->admin)->post("/invoices/{$invoice->id}/mark-paid", ['method' => 'cash', 'amount' => '150'])->assertInvalid('amount');
    $this->actingAs($this->admin)->post("/balances/{$this->customer->id}/pay", ['method' => 'cash', 'amount' => '0'])->assertInvalid('amount');

    expect(Payment::query()->count())->toBe(0);
});

test('with no amount given, the payment covers everything owed', function () {
    billOn($this->customer, '2026-09-01', 100);

    $this->actingAs($this->admin)->post("/balances/{$this->customer->id}/pay", ['method' => 'cash'])
        ->assertSessionHas('success', fn (string $m): bool => str_contains($m, 'all paid up'));

    expect(app(BillingService::class)->outstandingForCustomer($this->customer))->toBe(0.0);
});

test('a partly paid charge shows what\'s left and what it was', function () {
    billOn($this->customer, '2026-09-01', 100);
    app(RecordPayment::class)->handle($this->customer, 'cash', $this->admin->id, null, 30.0);

    $this->actingAs($this->admin)->get("/balances?selected={$this->customer->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.charges.0.amount', 70)
            ->where('selected.charges.0.charged', 100));
});
