<?php

declare(strict_types=1);

use App\Actions\GenerateInvoice;
use App\Actions\RecordPayment;
use App\Actions\SettleCheckoutSession;
use App\Mail\InvoiceMail;
use App\Mail\PaymentReceiptMail;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\BalanceReminder;
use App\Services\BillingService;
use App\Services\DailyOpsChecks;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * The billing system's guard rails: invoices go out by email when they're made,
 * money comes in through one path that can't count a payment twice, "overdue"
 * means money is still owed past the due date, and autopay dunning is one
 * charge plus spaced retries a month.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create(['email' => 'robert@anderson.test']);
});

/** A customer's invoice for one $50 charge. */
function hardeningInvoice(Customer $customer, float $amount = 50): Invoice
{
    manualCharge($customer, $amount, 'Weekly service');

    return app(GenerateInvoice::class)->handle($customer) ?? throw new RuntimeException('nothing billed');
}

/** Put the customer on autopay with a saved card. */
function onAutopay(Customer $customer): void
{
    config(['services.stripe.secret' => 'sk_test_x']);
    $pm = PaymentMethod::create(['customer_id' => $customer->id, 'stripe_payment_method_id' => 'pm_1', 'brand' => 'visa', 'last4' => '4242', 'is_default' => true]);
    $customer->forceFill(['autopay_enabled' => true, 'stripe_customer_id' => 'cus_1', 'default_payment_method_id' => $pm->id])->save();
}

function declinedOn(Customer $customer, string $at): void
{
    Payment::create(['customer_id' => $customer->id, 'amount' => 50, 'status' => 'failed', 'method' => 'card', 'failure_reason' => 'x'])
        ->forceFill(['created_at' => $at])->save();
}

// --- invoices go out by email ------------------------------------------------

test('the monthly run emails each new invoice; one with no address on file stays a draft', function () {
    Mail::fake();
    manualCharge($this->customer, 50, 'Weekly service');
    $noEmail = Customer::factory()->for($this->tenant)->create(['email' => null]);
    manualCharge($noEmail, 40, 'Weekly service');

    $this->artisan('app:generate-invoices')->expectsOutputToContain('Generated 2 invoice(s), emailed 1.')->assertSuccessful();

    $sent = Invoice::query()->where('customer_id', $this->customer->id)->sole();
    expect($sent->status)->toBe('sent')
        ->and(Invoice::query()->where('customer_id', $noEmail->id)->sole()->status)->toBe('draft');
    Mail::assertQueued(InvoiceMail::class, fn (InvoiceMail $m): bool => $m->hasTo('robert@anderson.test') && $m->invoice->is($sent));
    Mail::assertQueuedCount(1);
});

test('Invoice now bills, emails and audits in one step', function () {
    Mail::fake();
    manualCharge($this->customer, 50, 'Weekly service');

    $this->actingAs($this->admin)->post("/balances/{$this->customer->id}/invoice")
        ->assertSessionHas('success', fn (string $m): bool => str_contains($m, 'emailed to robert@anderson.test'));

    $invoice = Invoice::query()->sole();
    expect($invoice->status)->toBe('sent')
        ->and(AuditLog::query()->where('action', 'invoice.created')->where('model_id', $invoice->id)->exists())->toBeTrue();
    Mail::assertQueued(InvoiceMail::class);
});

test('Invoice now for a customer with no email address makes a draft and says so', function () {
    Mail::fake();
    $this->customer->update(['email' => null]);
    manualCharge($this->customer, 50, 'Weekly service');

    $this->actingAs($this->admin)->post("/balances/{$this->customer->id}/invoice")
        ->assertSessionHas('success', fn (string $m): bool => str_contains($m, 'draft'));

    expect(Invoice::query()->sole()->status)->toBe('draft');
    Mail::assertNothingQueued();
});

test('a voided invoice can\'t be emailed', function () {
    Mail::fake();
    $invoice = hardeningInvoice($this->customer);
    $this->actingAs($this->admin)->post("/invoices/{$invoice->id}/void")->assertSessionHas('success');

    $this->actingAs($this->admin)->post("/invoices/{$invoice->id}/email")->assertSessionHas('error');

    Mail::assertNothingQueued();
});

// --- overdue means money still owed past the due date ------------------------

test('an invoice with nothing left on it is never flagged overdue', function () {
    $invoice = hardeningInvoice($this->customer);
    $invoice->charges()->update(['voided_at' => now()]); // its only line was written off
    $invoice->update(['status' => 'sent', 'due_at' => now()->subDays(5)]);

    $this->artisan('app:flag-overdue-invoices')->assertSuccessful();

    expect($invoice->fresh()?->status)->toBe('sent');
});

test('a fully paid invoice past its due date reads paid, not overdue', function () {
    $invoice = hardeningInvoice($this->customer);
    Payment::create(['customer_id' => $this->customer->id, 'amount' => 50, 'status' => 'succeeded', 'method' => 'cash', 'paid_at' => now()]);
    $invoice->update(['status' => 'sent', 'due_at' => now()->subDays(5)]);

    $this->artisan('app:flag-overdue-invoices')->assertSuccessful();

    expect($invoice->fresh()?->status)->toBe('paid');
});

test('an archived customer\'s unpaid bill still goes overdue', function () {
    $invoice = hardeningInvoice($this->customer);
    $invoice->update(['status' => 'sent', 'due_at' => now()->subDays(5)]);
    $this->customer->delete();

    $this->artisan('app:flag-overdue-invoices')->expectsOutputToContain('Flagged 1 invoice(s)')->assertSuccessful();

    expect($invoice->fresh()?->status)->toBe('overdue');
});

test('a declined card doesn\'t make a bill overdue before its due date', function () {
    onAutopay($this->customer);
    $invoice = hardeningInvoice($this->customer);
    $invoice->update(['status' => 'sent']);
    Mail::fake();
    Http::fake(['api.stripe.com/v1/payment_intents' => Http::response(['error' => ['payment_intent' => ['id' => 'pi_no', 'status' => 'requires_payment_method']]], 402)]);

    $this->artisan('app:charge-autopay')->assertSuccessful();

    expect($invoice->fresh()?->status)->toBe('sent');
});

// --- one way money comes in ---------------------------------------------------

test('marking an invoice paid emails a receipt, like any other payment', function () {
    Mail::fake();
    $invoice = hardeningInvoice($this->customer);

    $this->actingAs($this->admin)->post("/invoices/{$invoice->id}/mark-paid", ['method' => 'check'])->assertSessionHas('success');

    expect($invoice->fresh()?->status)->toBe('paid');
    Mail::assertQueued(PaymentReceiptMail::class, fn (PaymentReceiptMail $m): bool => $m->amount === 50.0);
});

test('marking a paid invoice paid again records nothing', function () {
    $invoice = hardeningInvoice($this->customer);
    $this->actingAs($this->admin)->post("/invoices/{$invoice->id}/mark-paid", ['method' => 'cash']);

    $this->actingAs($this->admin)->post("/invoices/{$invoice->id}/mark-paid", ['method' => 'cash'])->assertSessionHas('error');

    expect(Payment::query()->count())->toBe(1);
});

test('the same card payment is only ever recorded once', function () {
    hardeningInvoice($this->customer, 100);
    $session = [
        'payment_status' => 'paid', 'payment_intent' => 'pi_1', 'amount_total' => 5000,
        'metadata' => ['tenant_id' => (string) $this->tenant->id, 'customer_id' => (string) $this->customer->id],
    ];

    app(SettleCheckoutSession::class)->handle($session);
    app(SettleCheckoutSession::class)->handle($session); // the webhook and the reconciler both saw it
    app(RecordPayment::class)->handle($this->customer, 'card', null, 'pi_1', 50.0); // or an autopay replay

    expect(Payment::query()->count())->toBe(1)
        ->and(app(BillingService::class)->outstandingForCustomer($this->customer))->toBe(50.0);
});

test('a receipt is dated by the company\'s calendar, not the server\'s', function () {
    Mail::fake();
    $this->tenant->update(['timezone' => 'Pacific/Honolulu']);
    Carbon::setTestNow('2026-10-19 05:00:00'); // still the 18th in Honolulu
    manualCharge($this->customer->fresh(), 50);

    app(RecordPayment::class)->handle($this->customer->fresh(), 'cash', $this->admin->id);

    Mail::assertQueued(PaymentReceiptMail::class, fn (PaymentReceiptMail $m): bool => $m->paidOn === 'Oct 18, 2026');
});

// --- autopay dunning -----------------------------------------------------------

test('the retry waits a day after a decline instead of replaying it', function () {
    onAutopay($this->customer);
    manualCharge($this->customer, 50);
    Carbon::setTestNow('2026-10-02 06:00:00');
    declinedOn($this->customer, '2026-10-02 05:00:00'); // the monthly charge, an hour ago
    Http::fake(['api.stripe.com/*' => Http::response(['id' => 'pi_x', 'status' => 'succeeded'], 200)]);

    $this->artisan('app:retry-autopay')->assertSuccessful();

    Http::assertNothingSent();
});

test('last month\'s declines don\'t start a retry this month', function () {
    onAutopay($this->customer);
    manualCharge($this->customer, 50);
    Carbon::setTestNow('2026-10-01 06:00:00');
    declinedOn($this->customer, '2026-09-29 06:00:00');
    Http::fake(['api.stripe.com/*' => Http::response(['id' => 'pi_x', 'status' => 'succeeded'], 200)]);

    $this->artisan('app:retry-autopay')->assertSuccessful();

    Http::assertNothingSent();
});

test('a suspended company\'s customers aren\'t charged', function () {
    onAutopay($this->customer);
    manualCharge($this->customer, 50);
    $this->tenant->forceFill(['status' => 'suspended'])->save();
    Http::fake(['api.stripe.com/*' => Http::response(['id' => 'pi_x', 'status' => 'succeeded'], 200)]);

    $this->artisan('app:charge-autopay')->assertSuccessful();

    Http::assertNothingSent();
});

// --- the homeowner's reminder ----------------------------------------------------

test('a homeowner with an overdue bill is reminded once a week, not every morning', function () {
    $portal = User::factory()->customer()->for($this->tenant)->create();
    $this->customer->update(['user_id' => $portal->id]);
    hardeningInvoice($this->customer)->update(['status' => 'overdue', 'due_at' => now()->subDays(10)]);

    app(DailyOpsChecks::class)->run($this->tenant->id);
    app(DailyOpsChecks::class)->run($this->tenant->id);
    expect($portal->notifications()->where('type', BalanceReminder::class)->count())->toBe(1);

    Carbon::setTestNow(now()->addDays(8));
    app(DailyOpsChecks::class)->run($this->tenant->id);
    expect($portal->notifications()->where('type', BalanceReminder::class)->count())->toBe(2);
});
