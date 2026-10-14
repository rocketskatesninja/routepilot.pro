<?php

declare(strict_types=1);

use App\Actions\AddManualCharge;
use App\Actions\CompleteVisit;
use App\Actions\CreateSubscription;
use App\Actions\DeleteSubscription;
use App\Actions\GenerateInvoice;
use App\Actions\MarkInvoicePaid;
use App\Actions\RecordPayment;
use App\Actions\SettleCheckoutSession;
use App\Actions\UpdateSubscription;
use App\Actions\VoidInvoice;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pool;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * The billing ledger: every completed visit records its own charge at its own
 * plan's price, and a customer's balance is simply charges (+ tax) − payments.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-06-08 12:00:00'); // Monday, 8 am Eastern
    Mail::fake();

    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant', $this->tenant);
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $this->clean = ServiceType::factory()->for($this->tenant)->create(['name' => 'Weekly Clean', 'price' => 50]);
    $this->chem = ServiceType::factory()->for($this->tenant)->create(['name' => 'Chemistry Check', 'price' => 20]);
    $this->billing = app(BillingService::class);
});

afterEach(fn () => Carbon::setTestNow());

function ledgerPlan(ServiceType $type, string $day = 'tuesday'): ServiceSubscription
{
    $t = test();

    return app(CreateSubscription::class)->handle([
        'pool_id' => $t->pool->id, 'service_type_id' => $type->id, 'assigned_agent_id' => $t->agent->id,
        'frequency' => 'weekly', 'preferred_day' => $day,
    ]);
}

/** Complete the plan's next pending visit through the field-app action. */
function completeNext(ServiceSubscription $plan): ServiceVisit
{
    $stop = RouteStop::query()->where('route_stops.service_subscription_id', $plan->id)->where('route_stops.status', 'pending')
        ->join('routes', 'routes.id', '=', 'route_stops.route_id')->orderBy('routes.scheduled_date')
        ->select('route_stops.*')->firstOrFail();

    return app(CompleteVisit::class)->handle($stop, ['notes' => null], test()->agent);
}

function balanceOf(Customer $c): float
{
    return app(BillingService::class)->outstandingForCustomer($c);
}

// --- what a visit costs ------------------------------------------------------

test('each visit is charged its own plan\'s price, even when a pool has two plans', function () {
    completeNext(ledgerPlan($this->clean));
    completeNext(ledgerPlan($this->chem));

    expect(Charge::query()->orderBy('amount')->pluck('amount')->map(fn ($a) => (float) $a)->all())->toBe([20.0, 50.0])
        ->and(balanceOf($this->customer))->toBe(70.0);
});

test('a visit remembers which plan it was for', function () {
    $plan = ledgerPlan($this->chem);

    expect(completeNext($plan)->service_subscription_id)->toBe($plan->id);
});

test('pausing or ending a plan never wipes out what is owed', function () {
    $plan = ledgerPlan($this->clean);
    completeNext($plan);

    app(UpdateSubscription::class)->handle($plan, ['status' => 'cancelled']);

    expect(balanceOf($this->customer))->toBe(50.0);
});

test('changing a service\'s price never reprices visits already done', function () {
    $plan = ledgerPlan($this->clean);
    completeNext($plan);

    $this->clean->update(['price' => 80]);
    completeNext($plan);

    expect(balanceOf($this->customer))->toBe(130.0); // 50 (before) + 80 (after)
});

test('saving a visit a second time (an edit) doesn\'t charge it twice', function () {
    $visit = completeNext(ledgerPlan($this->clean));
    app(CompleteVisit::class)->handle(RouteStop::query()->findOrFail($visit->route_stop_id), ['notes' => 'edited'], $this->agent);

    expect(Charge::query()->count())->toBe(1);
});

// --- tax ---------------------------------------------------------------------

test('the balance includes tax, so it matches the bill', function () {
    $this->tenant->forceFill(['tax_rate' => 0.10])->save();
    app(AddManualCharge::class)->handle(['customer_id' => $this->customer->id, 'description' => 'Filter', 'amount' => 100, 'taxable' => true], $this->admin->id);

    $invoice = app(GenerateInvoice::class)->handle($this->customer);

    expect(balanceOf($this->customer))->toBe(110.0)
        ->and((float) $invoice->total)->toBe(110.0)
        ->and((float) $invoice->tax)->toBe(10.0);
});

// --- invoices ----------------------------------------------------------------

test('monthly invoicing bills exactly the charges not yet billed, once', function () {
    $plan = ledgerPlan($this->clean);
    completeNext($plan);
    completeNext($plan);
    app(AddManualCharge::class)->handle(['customer_id' => $this->customer->id, 'description' => 'Salt', 'amount' => 15, 'taxable' => false], $this->admin->id);

    $invoice = app(GenerateInvoice::class)->handle($this->customer);

    expect((float) $invoice->total)->toBe(115.0)
        ->and($invoice->charges()->count())->toBe(3)
        ->and(app(GenerateInvoice::class)->handle($this->customer))->toBeNull();
});

test('voiding an invoice cancels its charges — it never records them as paid', function () {
    completeNext(ledgerPlan($this->clean));
    $invoice = app(GenerateInvoice::class)->handle($this->customer);

    app(VoidInvoice::class)->handle($invoice);

    expect(balanceOf($this->customer))->toBe(0.0)
        ->and(Payment::query()->count())->toBe(0)
        ->and(Charge::query()->whereNotNull('voided_at')->count())->toBe(1)
        ->and($invoice->fresh()->status)->toBe('void');
});

// --- payments ----------------------------------------------------------------

test('a partial payment lowers the balance and leaves the invoice open', function () {
    $plan = ledgerPlan($this->clean);
    completeNext($plan);
    completeNext($plan);
    $invoice = app(GenerateInvoice::class)->handle($this->customer); // $100

    app(RecordPayment::class)->handle($this->customer, 'check', $this->admin->id, null, 30.0);

    expect(balanceOf($this->customer))->toBe(70.0)
        ->and((float) $invoice->fresh()->amount_paid)->toBe(30.0)
        ->and($invoice->fresh()->status)->toBe('sent');
});

test('marking an invoice paid pays that invoice — not an older one', function () {
    $plan = ledgerPlan($this->clean);
    completeNext($plan);
    $older = app(GenerateInvoice::class)->handle($this->customer); // $50
    Carbon::setTestNow('2026-06-20 12:00:00');
    completeNext($plan);
    completeNext($plan);
    $newer = app(GenerateInvoice::class)->handle($this->customer); // $100

    app(MarkInvoicePaid::class)->handle($newer, 'cash', $this->admin->id);

    expect($newer->fresh()->status)->toBe('paid')
        ->and($older->fresh()->status)->not->toBe('paid')
        ->and(balanceOf($this->customer))->toBe(50.0);
});

test('a card checkout records what Stripe actually collected', function () {
    $plan = ledgerPlan($this->clean);
    completeNext($plan); // owed $50 when the customer opened checkout
    completeNext($plan); // another visit lands before Stripe confirms

    app(SettleCheckoutSession::class)->handle([
        'payment_status' => 'paid', 'payment_intent' => 'pi_test_1', 'amount_total' => 5000,
        'metadata' => ['tenant_id' => (string) $this->tenant->id, 'customer_id' => (string) $this->customer->id],
    ]);

    expect((float) Payment::query()->sole()->amount)->toBe(50.0)
        ->and(balanceOf($this->customer))->toBe(50.0);
});

// --- plans keep their history -------------------------------------------------

test('a plan with completed visits can\'t be deleted, even if its route stops are gone', function () {
    $plan = ledgerPlan($this->clean);
    $visit = completeNext($plan);
    RouteStop::query()->whereKey($visit->route_stop_id)->delete();

    expect(app(DeleteSubscription::class)->handle($plan, $this->admin))->toBeFalse()
        ->and(Invoice::query()->count())->toBe(0);
});
