<?php

declare(strict_types=1);

use App\Mail\PaymentFailedMail;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Pool;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config(['services.stripe.secret' => 'sk_test_x']);
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $agent = User::factory()->agent()->for($this->tenant)->create();
    $this->admin = User::factory()->for($this->tenant)->create();

    $this->customer = Customer::factory()->for($this->tenant)->create(['email' => 'h@x.test']);
    $pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $type = ServiceType::factory()->for($this->tenant)->create(['price' => 50]);
    ServiceSubscription::factory()->for($this->tenant)->for($pool)->for($type)->create(['status' => 'active', 'assigned_agent_id' => $agent->id]);
    ServiceVisit::factory()->for($this->tenant)->for($pool)->create(['status' => 'completed', 'paid_at' => null, 'completed_at' => now(), 'agent_id' => $agent->id]);
    // outstanding balance = $50

    $pm = PaymentMethod::create(['customer_id' => $this->customer->id, 'stripe_payment_method_id' => 'pm_1', 'brand' => 'visa', 'last4' => '4242', 'is_default' => true]);
    $this->customer->forceFill(['autopay_enabled' => true, 'stripe_customer_id' => 'cus_1', 'default_payment_method_id' => $pm->id])->save();
});

test('autopay charges the saved card and settles the balance', function () {
    Http::fake(['api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_ok', 'status' => 'succeeded'], 200)]);

    $this->artisan('app:charge-autopay')->assertSuccessful();

    expect(app(BillingService::class)->outstandingForCustomer($this->customer->fresh()))->toBe(0.0);
    expect(Payment::query()->where('customer_id', $this->customer->id)->where('stripe_payment_intent_id', 'pi_ok')->exists())->toBeTrue();
});

test('a declined autopay charge duns the customer', function () {
    Mail::fake();
    Http::fake(['api.stripe.com/v1/payment_intents' => Http::response(['error' => ['payment_intent' => ['id' => 'pi_no', 'status' => 'requires_payment_method']]], 402)]);

    $this->artisan('app:charge-autopay')->assertSuccessful();

    expect(app(BillingService::class)->outstandingForCustomer($this->customer->fresh()))->toBe(50.0);
    expect(Payment::query()->where('customer_id', $this->customer->id)->where('status', 'failed')->exists())->toBeTrue();
    Mail::assertQueued(PaymentFailedMail::class);
    expect($this->admin->notifications()->count())->toBe(1);
});

test('retry recharges a recently-declined customer and recovers', function () {
    Payment::create(['customer_id' => $this->customer->id, 'amount' => 50, 'status' => 'failed', 'method' => 'card', 'failure_reason' => 'x']);
    Http::fake(['api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_retry', 'status' => 'succeeded'], 200)]);

    $this->artisan('app:retry-autopay')->assertSuccessful();

    expect(app(BillingService::class)->outstandingForCustomer($this->customer->fresh()))->toBe(0.0);
});

test('retry gives up after three failures', function () {
    for ($i = 0; $i < 3; $i++) {
        Payment::create(['customer_id' => $this->customer->id, 'amount' => 50, 'status' => 'failed', 'method' => 'card', 'failure_reason' => 'x']);
    }
    Http::fake(['api.stripe.com/*' => Http::response(['id' => 'pi_x', 'status' => 'succeeded'], 200)]);

    $this->artisan('app:retry-autopay')->assertSuccessful();

    Http::assertNothingSent();
    expect(app(BillingService::class)->outstandingForCustomer($this->customer->fresh()))->toBe(50.0);
});

test('a customer without autopay is not charged', function () {
    $this->customer->forceFill(['autopay_enabled' => false])->save();
    Http::fake(['api.stripe.com/*' => Http::response([], 200)]);

    $this->artisan('app:charge-autopay')->assertSuccessful();

    Http::assertNothingSent();
    expect(app(BillingService::class)->outstandingForCustomer($this->customer->fresh()))->toBe(50.0);
});
