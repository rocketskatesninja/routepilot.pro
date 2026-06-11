<?php

declare(strict_types=1);

use App\Actions\RecordPayment;
use App\Mail\PaymentReceiptMail;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Pool;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['services.stripe.secret' => 'sk_test_x', 'services.stripe.webhook_secret' => 'whsec_test']);

    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->user = User::factory()->customer()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create();
    $this->customer->forceFill(['user_id' => $this->user->id])->save();

    $pool = Pool::factory()->for($this->tenant)->for($this->customer)->create();
    $type = ServiceType::factory()->for($this->tenant)->create(['price' => 60]);
    ServiceSubscription::factory()->for($this->tenant)->for($pool)->for($type)->create(['status' => 'active', 'assigned_agent_id' => $agent->id]);
    ServiceVisit::factory()->for($this->tenant)->for($pool)->create(['status' => 'completed', 'paid_at' => null, 'completed_at' => now(), 'agent_id' => $agent->id]);
    // outstanding balance = 1 completed visit x $60 = $60
});

function stripeSigned(string $payload): TestResponse
{
    $t = '1700000000';
    $sig = hash_hmac('sha256', $t.'.'.$payload, 'whsec_test');

    return test()->call('POST', '/stripe/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => "t={$t},v1={$sig}", 'CONTENT_TYPE' => 'application/json',
    ], $payload);
}

test('the customer balance page shows the breakdown', function () {
    $this->actingAs($this->user)->get('/balance')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('portal/Balance')->where('total', 60)->where('can_pay', true));
});

test('paying starts a Stripe Checkout and redirects there', function () {
    Http::fake(['api.stripe.com/*' => Http::response(['url' => 'https://checkout.stripe.test/sess_1'], 200)]);

    $this->actingAs($this->user)->post('/balance/pay')->assertRedirect('https://checkout.stripe.test/sess_1');

    Http::assertSent(fn ($req) => str_contains($req->url(), 'checkout/sessions'));
});

test('a completed checkout webhook settles the balance', function () {
    $payload = json_encode([
        'id' => 'evt_pay_1',
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'payment_status' => 'paid',
            'payment_intent' => 'pi_123',
            'metadata' => ['customer_id' => (string) $this->customer->id, 'tenant_id' => (string) $this->tenant->id],
        ]],
    ]);

    stripeSigned($payload)->assertOk();

    expect(app(BillingService::class)->outstandingForCustomer($this->customer->fresh()))->toBe(0.0);
    $payment = Payment::query()->where('customer_id', $this->customer->id)->where('method', 'card')->first();
    expect($payment)->not->toBeNull();
    expect($payment?->getAttribute('stripe_payment_intent_id'))->toBe('pi_123');
});

test('the same checkout event is not settled twice (idempotent)', function () {
    $payload = json_encode([
        'id' => 'evt_pay_dupe',
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'payment_status' => 'paid', 'payment_intent' => 'pi_9',
            'metadata' => ['customer_id' => (string) $this->customer->id, 'tenant_id' => (string) $this->tenant->id],
        ]],
    ]);

    stripeSigned($payload)->assertOk();
    stripeSigned($payload)->assertOk();

    expect(Payment::query()->where('customer_id', $this->customer->id)->count())->toBe(1);
});

test('settling a payment emails the customer a receipt', function () {
    Mail::fake();

    app(RecordPayment::class)->handle($this->customer, 'card', null, 'pi_receipt');

    Mail::assertQueued(PaymentReceiptMail::class);
});
