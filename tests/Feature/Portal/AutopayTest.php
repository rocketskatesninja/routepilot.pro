<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.stripe.secret' => 'sk_test_x']);
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->user = User::factory()->customer()->for($this->tenant)->create();
    $this->customer = Customer::factory()->for($this->tenant)->create(['email' => 'h@x.test']);
    $this->customer->forceFill(['user_id' => $this->user->id])->save();
});

test('setting up autopay creates a Stripe customer and redirects to setup Checkout', function () {
    Http::fake([
        'api.stripe.com/v1/customers' => Http::response(['id' => 'cus_1'], 200),
        'api.stripe.com/v1/checkout/sessions' => Http::response(['url' => 'https://checkout.stripe.test/setup_1'], 200),
    ]);

    $this->actingAs($this->user)->post('/autopay/setup')->assertRedirect('https://checkout.stripe.test/setup_1');

    expect($this->customer->fresh()?->getAttribute('stripe_customer_id'))->toBe('cus_1');
});

test('completing setup saves the card and enables autopay', function () {
    Http::fake([
        'api.stripe.com/v1/checkout/sessions/cs_1*' => Http::response([
            'setup_intent' => ['payment_method' => ['id' => 'pm_1', 'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030]]],
        ], 200),
    ]);

    $this->actingAs($this->user)->get('/autopay/complete?session_id=cs_1')->assertRedirect('/balance');

    $pm = PaymentMethod::query()->where('customer_id', $this->customer->id)->first();
    expect($pm?->getAttribute('stripe_payment_method_id'))->toBe('pm_1');
    expect($pm?->last4)->toBe('4242');

    $fresh = $this->customer->fresh();
    expect((bool) $fresh?->getAttribute('autopay_enabled'))->toBeTrue();
    expect($fresh?->getAttribute('default_payment_method_id'))->toBe($pm?->id);
});

test('disabling autopay turns it off', function () {
    $this->customer->forceFill(['autopay_enabled' => true])->save();

    $this->actingAs($this->user)->post('/autopay/disable')->assertRedirect();

    expect((bool) $this->customer->fresh()?->getAttribute('autopay_enabled'))->toBeFalse();
});

test('the balance page reflects a saved card', function () {
    $pm = PaymentMethod::create([
        'customer_id' => $this->customer->id, 'stripe_payment_method_id' => 'pm_x',
        'brand' => 'visa', 'last4' => '4242', 'is_default' => true,
    ]);
    $this->customer->forceFill(['autopay_enabled' => true, 'default_payment_method_id' => $pm->id])->save();

    $this->actingAs($this->user)->get('/balance')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('autopay', true)->where('card.last4', '4242'));
});
