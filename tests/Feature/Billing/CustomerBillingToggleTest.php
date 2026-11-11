<?php

declare(strict_types=1);

use App\Actions\GenerateInvoice;
use App\Actions\LogEquipmentService;
use App\Actions\RegisterTenant;
use App\Actions\SaveDashboardLayout;
use App\Actions\SendVisitFollowups;
use App\Dashboard\Advisories;
use App\Dashboard\DashboardWidgets;
use App\Mail\VisitRecapMail;
use App\Models\AuditLog;
use App\Models\Charge;
use App\Models\ChemicalInventory;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Pool;
use App\Models\PoolEquipment;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\BalanceReminder;
use App\Notifications\OpsAlert;
use App\Services\Chat\TenantContext;
use App\Services\Chat\Tools\LookupBalance;
use App\Services\DailyOpsChecks;
use App\Support\EmailPreferences;
use App\Support\NotificationTypes;
use App\Support\OnboardingStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/*
 * A company can turn customer billing off: it stops billing its customers
 * through RoutePilot, without losing what's already on record. Its own
 * RoutePilot subscription is a separate thing and isn't affected.
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->customerUser = User::factory()->customer()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create(['user_id' => $this->customerUser->id, 'email' => 'homeowner@example.com']);
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create(['name' => 'Backyard pool']);
    $this->plan = ServiceSubscription::factory()->for($this->tenant)->for($this->pool)
        ->for(ServiceType::factory()->for($this->tenant)->create(['price' => 50]))
        ->create(['assigned_agent_id' => $this->agent->id, 'status' => 'active']);
});

function billingOff(Tenant $tenant): void
{
    $tenant->forceFill(['customer_billing' => false])->save();
}

/** Run something as the given company, then go back to the test's own. */
function asCompany(Tenant $tenant, callable $do): mixed
{
    app()->instance('tenant_id', $tenant->id);
    $out = $do();
    app()->instance('tenant_id', test()->tenant->id);

    return $out;
}

/** Another company with a customer who owes $50 and is on autopay. */
function owingCompany(): array
{
    $tenant = Tenant::factory()->create();

    return [$tenant, asCompany($tenant, function () use ($tenant): Customer {
        $agent = User::factory()->agent()->for($tenant)->create();
        $customer = Customer::factory()->for($tenant)->create(['email' => 'owes@example.com']);
        $pool = Pool::factory()->for($tenant)->for($customer)->create();
        $type = ServiceType::factory()->for($tenant)->create(['price' => 50]);
        chargedVisit(ServiceSubscription::factory()->for($tenant)->for($pool)->for($type)->create(['status' => 'active', 'assigned_agent_id' => $agent->id]));
        $card = PaymentMethod::create(['customer_id' => $customer->id, 'stripe_payment_method_id' => 'pm_'.$customer->id, 'brand' => 'visa', 'last4' => '4242', 'is_default' => true]);
        $customer->forceFill(['autopay_enabled' => true, 'stripe_customer_id' => 'cus_'.$customer->id, 'default_payment_method_id' => $card->id])->save();

        return $customer;
    })];
}

/** Whether anything of this kind exists for the customer, whichever company is bound. */
function invoiced(Customer $customer): bool
{
    return Invoice::withoutGlobalScopes()->where('customer_id', $customer->id)->exists();
}

/** The tech finishes a stop on this test's pool. */
function finishVisit(array $fields = []): ServiceVisit
{
    $route = Route::forDay(test()->tenant->id, test()->agent->id, test()->tenant->today()->toDateString());
    $stop = RouteStop::factory()->for($route)->for(test()->pool)->create(['service_subscription_id' => test()->plan->id, 'status' => 'pending']);
    test()->actingAs(test()->agent)->post("/api/field/visits/{$stop->id}/complete", ['ph' => 7.4, ...$fields])->assertOk();

    return ServiceVisit::query()->where('route_stop_id', $stop->id)->sole();
}

/** A bill this test's customer is late on. */
function overdueBill(): Invoice
{
    chargedVisit(test()->plan);
    $invoice = app(GenerateInvoice::class)->handle(test()->customer);
    $invoice?->update(['status' => 'overdue', 'due_at' => now()->subDays(10)]);

    return $invoice;
}

test('every company starts with it on', function () {
    expect($this->tenant->customer_billing)->toBeTrue()
        ->and($this->tenant->fresh()?->customer_billing)->toBeTrue();

    $user = app(RegisterTenant::class)(['company' => 'New Pools', 'first_name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'a-long-password']);

    expect($user->tenant?->customer_billing)->toBeTrue();
});

test('an admin can turn it off and on, and each change is on the audit log', function () {
    $this->actingAs($this->admin)->patch('/company/features', ['customer_billing' => false])->assertRedirect();

    expect($this->tenant->fresh()?->customer_billing)->toBeFalse()
        ->and(AuditLog::query()->where('action', 'company.customer_billing')->count())->toBe(1);

    // Saving what's already set isn't a change.
    $this->actingAs($this->admin)->patch('/company/features', ['customer_billing' => false])->assertRedirect();
    expect(AuditLog::query()->where('action', 'company.customer_billing')->count())->toBe(1);

    $this->actingAs($this->admin)->patch('/company/features', ['customer_billing' => true])->assertRedirect();
    expect($this->tenant->fresh()?->customer_billing)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'company.customer_billing')->count())->toBe(2);
});

test('only the company’s admin can change it, and only for their own company', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();
    $this->actingAs($agent)->patch('/company/features', ['customer_billing' => false])->assertForbidden();
    $this->actingAs($this->customerUser)->patch('/company/features', ['customer_billing' => false])->assertForbidden();
    expect($this->tenant->fresh()?->customer_billing)->toBeTrue();

    $other = Tenant::factory()->create();
    $this->actingAs($this->admin)->patch('/company/features', ['customer_billing' => false])->assertRedirect();
    expect($other->fresh()?->customer_billing)->toBeTrue();
});

test('it has to be a yes or no', function () {
    $this->actingAs($this->admin)->patch('/company/features', ['customer_billing' => 'maybe'])->assertSessionHasErrors('customer_billing');
    $this->actingAs($this->admin)->patch('/company/features', [])->assertSessionHasErrors('customer_billing');
    expect($this->tenant->fresh()?->customer_billing)->toBeTrue();
});

test('the billing screens are there while it’s on', function () {
    $this->actingAs($this->admin)->get('/balances')->assertOk();
    $this->actingAs($this->customerUser)->get('/balance')->assertOk();
});

test('with it off, the office’s billing screens and actions are gone', function () {
    billingOff($this->tenant);

    $this->actingAs($this->admin)->get('/balances')->assertNotFound();
    $this->actingAs($this->admin)->get('/balances/export')->assertNotFound();
    $this->actingAs($this->admin)->post('/balances/charges', [])->assertNotFound();
    $this->actingAs($this->admin)->post("/balances/{$this->customer->id}/pay", [])->assertNotFound();
    $this->actingAs($this->admin)->post("/balances/{$this->customer->id}/invoice", [])->assertNotFound();
});

test('with it off, the customer’s balance, pay and autopay pages are gone', function () {
    billingOff($this->tenant);

    $this->actingAs($this->customerUser)->get('/balance')->assertNotFound();
    $this->actingAs($this->customerUser)->post('/balance/pay')->assertNotFound();
    $this->actingAs($this->customerUser)->post('/autopay/setup')->assertNotFound();
    $this->actingAs($this->customerUser)->post('/autopay/disable')->assertNotFound();
});

test('with it off, a pay link from an old email still works', function () {
    billingOff($this->tenant);

    // Paid up, so it lands on the thanks page: the link itself isn't refused.
    $this->get(URL::signedRoute('pay.link', ['customer' => $this->customer->id]))->assertRedirect('/pay/thanks');
});

test('the switch reaches the app, for every signed-in role', function () {
    billingOff($this->tenant);

    $this->actingAs($this->admin)->get('/company')->assertInertia(fn ($page) => $page->where('tenant.customer_billing', false));
    $this->actingAs($this->customerUser)->get('/dashboard')->assertInertia(fn ($page) => $page->where('tenant.customer_billing', false));
});

test('with it off, a finished visit bills nothing, and what it used still comes off the shelf', function () {
    Mail::fake();
    billingOff($this->tenant);
    $this->customer->update(['bill_chemicals' => true]);
    $acid = ChemicalInventory::factory()->for($this->tenant)->create(['unit' => 'gal', 'current_stock' => 20, 'sell_price' => 4.50]);
    $part = ChemicalInventory::factory()->for($this->tenant)->create(['kind' => 'part', 'unit' => 'unit', 'current_stock' => 5, 'sell_price' => 12]);

    $visit = finishVisit(['treatments' => [['chemical_id' => $acid->id, 'amount' => 2]], 'parts' => [['part_id' => $part->id, 'amount' => 1]]]);

    expect(Charge::query()->count())->toBe(0)
        ->and($visit->treatments()->count())->toBe(2)
        ->and((float) $acid->fresh()?->current_stock)->toBe(18.0)
        ->and((float) $part->fresh()?->current_stock)->toBe(4.0);
});

test('with it on, the same visit is billed as ever', function () {
    Mail::fake();

    finishVisit();

    expect(Charge::query()->where('kind', 'visit')->count())->toBe(1);
});

test('with it off, what was billed before is left alone', function () {
    Mail::fake();
    finishVisit();
    billingOff($this->tenant);

    expect(Charge::query()->count())->toBe(1);
    $this->actingAs($this->customerUser)->get('/dashboard')->assertOk();
    expect(Charge::query()->count())->toBe(1);
});

test('with it off, the visit email has no balance and no pay link', function () {
    Mail::fake();
    $visit = chargedVisit($this->plan);
    billingOff($this->tenant);

    app(SendVisitFollowups::class)->handle($visit->fresh());

    Mail::assertQueued(VisitRecapMail::class, function (VisitRecapMail $mail): bool {
        $html = $mail->render();

        return $mail->balance === 0.0 && $mail->payUrl === null && ! str_contains($html, 'Your balance') && ! str_contains($html, '/pay/');
    });
});

test('with it on, the visit email shows what’s owed and how to pay', function () {
    Mail::fake();
    $visit = chargedVisit($this->plan);

    app(SendVisitFollowups::class)->handle($visit->fresh());

    Mail::assertQueued(VisitRecapMail::class, fn (VisitRecapMail $mail): bool => $mail->balance === 50.0 && $mail->payUrl !== null);
});

test('with it off, a repair logged on equipment isn’t billed', function () {
    billingOff($this->tenant);
    $equipment = PoolEquipment::create(['pool_id' => $this->pool->id, 'type' => 'pump']);

    app(LogEquipmentService::class)->handle($equipment, ['description' => 'New seal', 'cost' => 80, 'bill' => true], $this->admin->id);

    expect($equipment->serviceLog()->count())->toBe(1)
        ->and(Charge::query()->count())->toBe(0);
});

test('with it off, a finished work order can’t be billed', function () {
    $order = WorkOrder::factory()->for($this->tenant)->create(['pool_id' => $this->pool->id, 'status' => 'done']);
    expect($this->admin->can('bill', $order))->toBeTrue();

    billingOff($this->tenant);

    expect($this->admin->fresh()?->can('bill', $order))->toBeFalse();
});

test('the monthly invoices skip a company with it off, and not the others', function () {
    Mail::fake();
    [$off, $offCustomer] = owingCompany();
    [, $onCustomer] = owingCompany();
    billingOff($off);

    $this->artisan('app:generate-invoices')->assertSuccessful();

    expect(invoiced($offCustomer))->toBeFalse()
        ->and(invoiced($onCustomer))->toBeTrue();
});

test('autopay skips a company with it off, and not the others', function () {
    Mail::fake();
    config(['services.stripe.secret' => 'sk_test_x']);
    Http::fake(['api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_ok', 'status' => 'succeeded'], 200)]);
    [$off, $offCustomer] = owingCompany();
    [, $onCustomer] = owingCompany();
    billingOff($off);

    $this->artisan('app:charge-autopay')->assertSuccessful();

    Http::assertSentCount(1);
    expect(Payment::withoutGlobalScopes()->where('customer_id', $offCustomer->id)->exists())->toBeFalse()
        ->and(Payment::withoutGlobalScopes()->where('customer_id', $onCustomer->id)->where('status', 'succeeded')->exists())->toBeTrue();
});

test('autopay retries skip a company with it off, and not the others', function () {
    Mail::fake();
    config(['services.stripe.secret' => 'sk_test_x']);
    Http::fake(['api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_ok', 'status' => 'succeeded'], 200)]);
    $this->travelTo(now()->startOfMonth()->addDays(10));
    [$off, $offCustomer] = owingCompany();
    [$on, $onCustomer] = owingCompany();
    foreach ([[$off, $offCustomer], [$on, $onCustomer]] as [$tenant, $customer]) {
        asCompany($tenant, fn () => Payment::create(['customer_id' => $customer->id, 'amount' => 50, 'status' => 'failed', 'method' => 'card', 'failure_reason' => 'x'])
            ->forceFill(['created_at' => now()->subDays(2)])->save());
    }
    billingOff($off);

    $this->artisan('app:retry-autopay')->assertSuccessful();

    Http::assertSentCount(1);
    expect(Payment::withoutGlobalScopes()->where('customer_id', $onCustomer->id)->where('status', 'succeeded')->exists())->toBeTrue();
});

test('overdue flagging skips a company with it off, and not the others', function () {
    [$off, $offCustomer] = owingCompany();
    [$on, $onCustomer] = owingCompany();
    $late = fn (Tenant $tenant, Customer $customer) => asCompany($tenant, function () use ($customer): ?Invoice {
        $invoice = app(GenerateInvoice::class)->handle($customer);
        $invoice?->update(['status' => 'sent', 'due_at' => now()->subDays(10)]);

        return $invoice;
    });
    $offInvoice = $late($off, $offCustomer);
    $onInvoice = $late($on, $onCustomer);
    billingOff($off);

    $this->artisan('app:flag-overdue-invoices')->assertSuccessful();

    expect(Invoice::withoutGlobalScopes()->find($offInvoice->id)?->status)->toBe('sent')
        ->and(Invoice::withoutGlobalScopes()->find($onInvoice->id)?->status)->toBe('overdue');
});

test('with it off, nobody is reminded about an overdue bill', function () {
    Notification::fake();
    overdueBill();

    billingOff($this->tenant);
    app(DailyOpsChecks::class)->run($this->tenant->id);

    Notification::assertNotSentTo($this->customerUser, BalanceReminder::class);
    Notification::assertNotSentTo($this->admin, OpsAlert::class, fn (OpsAlert $alert): bool => str_contains(json_encode($alert) ?: '', 'overdue_balances'));
});

test('with it on, the customer and the office hear about an overdue bill', function () {
    Notification::fake();
    overdueBill();

    app(DailyOpsChecks::class)->run($this->tenant->id);

    Notification::assertSentTo($this->customerUser, BalanceReminder::class);
    Notification::assertSentTo($this->admin, OpsAlert::class, fn (OpsAlert $alert): bool => str_contains(json_encode($alert) ?: '', 'overdue_balances'));
});

test('the overdue advisory goes with it', function () {
    overdueBill();
    $mentionsBill = fn (): bool => collect(Advisories::forAdmin($this->admin->fresh(), null))->contains(fn (array $a): bool => str_contains($a['msg'], 'past due'));

    expect($mentionsBill())->toBeTrue();

    billingOff($this->tenant);

    expect($mentionsBill())->toBeFalse();
});

test('billing widgets leave dashboards with it off, and come back with it on', function () {
    app(SaveDashboardLayout::class)->handle($this->customerUser, 'desktop', [
        ['i' => 'pay_bill', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4],
        ['i' => 'next_visit', 'x' => 6, 'y' => 0, 'w' => 6, 'h' => 3],
    ]);
    $user = fn () => User::query()->findOrFail($this->customerUser->id);
    $placed = fn (string $mode): array => collect(DashboardWidgets::layoutsFor($user())[$mode])->pluck('i')->all();

    expect($placed('desktop'))->toContain('pay_bill');

    billingOff($this->tenant);

    expect($placed('desktop'))->toBe(['next_visit'])
        ->and($placed('mobile'))->not->toContain('pay_bill')
        ->and(collect(DashboardWidgets::palette($user()))->pluck('key'))->not->toContain('pay_bill', 'account_balance');
    $admin = User::query()->findOrFail($this->admin->id);
    expect(collect(DashboardWidgets::palette($admin))->pluck('key'))->not->toContain('billing_summary')
        ->and(DashboardWidgets::keysFor($admin))->not->toContain('billing_summary');

    $this->tenant->forceFill(['customer_billing' => true])->save();

    expect($placed('desktop'))->toContain('pay_bill');
});

test('with it off, the billing notification switches are gone', function () {
    expect(NotificationTypes::for($this->admin))->toHaveKeys(['autopay_declined', 'overdue_balances'])
        ->and(NotificationTypes::for($this->customerUser))->toHaveKey('balance_reminder');

    billingOff($this->tenant);
    $admin = User::query()->findOrFail($this->admin->id);
    $customer = User::query()->findOrFail($this->customerUser->id);

    expect(NotificationTypes::for($admin))->not->toHaveKeys(['autopay_declined', 'overdue_balances'])
        ->and(NotificationTypes::for($customer))->not->toHaveKey('balance_reminder')
        ->and(NotificationTypes::for($admin))->toHaveKey('missed_stops');
});

test('the assistant knows, too', function () {
    overdueBill();
    expect(app(TenantContext::class)->build($this->tenant->fresh()))->toContain('invoices are overdue');

    billingOff($this->tenant);

    expect(app(TenantContext::class)->build($this->tenant->fresh()))->not->toContain('invoices are overdue')
        ->and((new LookupBalance)->availableTo($this->admin))->toBeFalse(); // not offered at all
});

test('the field app is told, so it can leave prices off offline', function () {
    $this->actingAs($this->agent)->getJson('/api/field/today')->assertOk()->assertJsonPath('billing', true);

    billingOff($this->tenant);

    $this->actingAs(User::query()->findOrFail($this->agent->id))->getJson('/api/field/today')->assertOk()->assertJsonPath('billing', false);
});

test('the people export drops its Balance column with it off', function () {
    $csv = fn () => $this->actingAs(User::query()->findOrFail($this->admin->id))->get('/people/export?format=csv')->assertOk()->streamedContent();

    expect($csv())->toContain('Balance');

    billingOff($this->tenant);

    expect($csv())->not->toContain('Balance');
    $this->actingAs(User::query()->findOrFail($this->admin->id))->get('/people/export?format=pdf')->assertOk();
});

test('the page linked from emails knows, even with nobody signed in', function () {
    $page = fn () => $this->get(EmailPreferences::pageUrl($this->customer->id));

    $page()->assertInertia(fn ($p) => $p->where('billing', true));

    billingOff($this->tenant);

    $page()->assertInertia(fn ($p) => $p->where('billing', false));
});

test('the company’s public website is told, so it can drop its payment badges', function () {
    billingOff($this->tenant);

    $this->get('/t/'.$this->tenant->slug)->assertOk()->assertInertia(fn ($p) => $p->where('tenant.customer_billing', false));
});

test('a new company can answer from its dashboard checklist without it counting as a step', function () {
    $checklist = fn (): array => OnboardingStatus::for($this->tenant->fresh());
    $before = $checklist();

    $this->actingAs($this->admin)->from('/dashboard')->patch('/company/features', ['customer_billing' => false])->assertRedirect('/dashboard');

    expect($checklist()['done'])->toBe($before['done'])
        ->and($checklist()['total'])->toBe($before['total'])
        ->and(collect($checklist()['steps'])->pluck('key'))->not->toContain('customer_billing');
    $this->actingAs(User::query()->findOrFail($this->admin->id))->get('/dashboard')->assertOk()
        ->assertInertia(fn ($p) => $p->where('tenant.customer_billing', false));
});

test('without the bill beside it, a customer’s next visit fills the row', function () {
    $width = fn (): int => collect(DashboardWidgets::layoutsFor(User::query()->findOrFail($this->customerUser->id))['desktop'])->firstWhere('i', 'next_visit')['w'];

    expect($width())->toBe(3);

    billingOff($this->tenant);

    expect($width())->toBe(6);
});
