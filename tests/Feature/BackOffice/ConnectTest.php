<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.stripe.secret' => 'sk_test_x', 'services.stripe.application_fee_percent' => 0.5]);
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
});

test('an admin starts Stripe Connect onboarding', function () {
    Http::fake([
        'api.stripe.com/v1/accounts' => Http::response(['id' => 'acct_1'], 200),
        'api.stripe.com/v1/account_links' => Http::response(['url' => 'https://connect.stripe.test/onboard'], 200),
    ]);

    $this->actingAs($this->admin)->post('/company/connect')->assertRedirect('https://connect.stripe.test/onboard');

    expect($this->tenant->fresh()?->getAttribute('stripe_connect_account_id'))->toBe('acct_1');
});

test('the connect return stores charges-enabled status', function () {
    $this->tenant->forceFill(['stripe_connect_account_id' => 'acct_1'])->save();
    Http::fake(['api.stripe.com/v1/accounts/acct_1' => Http::response(['charges_enabled' => true], 200)]);

    $this->actingAs($this->admin)->get('/company/connect/return')->assertRedirect('/company');

    expect((bool) $this->tenant->fresh()?->getAttribute('stripe_connect_charges_enabled'))->toBeTrue();
});

test('payments route to the connected account as a destination charge', function () {
    $this->tenant->forceFill(['stripe_connect_account_id' => 'acct_1', 'stripe_connect_charges_enabled' => true])->save();
    $customer = Customer::factory()->for($this->tenant)->create(['email' => 'h@x.test']);

    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['url' => 'https://checkout.stripe.test/x'], 200)]);

    app(StripeService::class)->createBalanceCheckout($customer, 100.0, 'https://ok', 'https://cancel');

    Http::assertSent(function ($request): bool {
        $data = $request->data();

        return ($data['payment_intent_data[transfer_data][destination]'] ?? null) === 'acct_1'
            && (int) ($data['payment_intent_data[application_fee_amount]'] ?? 0) === 50; // 0.5% of $100
    });
});

test('payments stay on the platform when the tenant is not connected', function () {
    $customer = Customer::factory()->for($this->tenant)->create(['email' => 'h@x.test']);
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['url' => 'https://checkout.stripe.test/x'], 200)]);

    app(StripeService::class)->createBalanceCheckout($customer, 100.0, 'https://ok', 'https://cancel');

    Http::assertSent(fn ($request): bool => ! isset($request->data()['payment_intent_data[transfer_data][destination]']));
});
