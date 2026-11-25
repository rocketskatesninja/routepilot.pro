<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Csv;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
});

/** Staff with confirmed 2FA; returns [secret, recoveryCodes]. */
function hardenTwoFactor(User $user): array
{
    $secret = app(Google2FA::class)->generateSecretKey();
    $codes = ['AAAAA-BBBBB', 'CCCCC-DDDDD'];
    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => json_encode($codes),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return [$secret, $codes];
}

test('a signed-in user is signed out once deactivated', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)->get('/dashboard')->assertOk();

    $agent->forceFill(['is_active' => false])->save();

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a deactivated user gets a 401 from the field api', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();
    $this->actingAs($agent);
    $agent->forceFill(['is_active' => false])->save();

    $this->getJson('/api/field/today')->assertUnauthorized();
});

test('google sign-in still owes the two-factor code', function () {
    $this->admin->forceFill(['google_id' => 'g-2fa', 'email_verified_at' => now()])->save();
    [$secret] = hardenTwoFactor($this->admin);

    $google = new SocialiteUser;
    $google->id = 'g-2fa';
    $google->email = $this->admin->email;
    $google->user = ['email_verified' => true];
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($google);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('auth.google.callback'))->assertRedirect(route('two-factor.challenge'));
    $this->assertGuest();

    $this->post('/two-factor-challenge', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($this->admin);
});

test('turning off two-factor needs a current code', function () {
    [$secret] = hardenTwoFactor($this->admin);

    $this->actingAs($this->admin)->delete('/settings/two-factor')->assertSessionHasErrors('code');
    $this->delete('/settings/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');
    expect($this->admin->refresh()->hasTwoFactorEnabled())->toBeTrue();

    $this->delete('/settings/two-factor', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])
        ->assertSessionHasNoErrors();
    expect($this->admin->refresh()->hasTwoFactorEnabled())->toBeFalse();
});

test('regenerating recovery codes needs a current code, and a recovery code is used up', function () {
    hardenTwoFactor($this->admin);

    $this->actingAs($this->admin)->post('/settings/two-factor/recovery-codes')->assertSessionHasErrors('code');
    expect($this->admin->refresh()->recoveryCodes())->toBe(['AAAAA-BBBBB', 'CCCCC-DDDDD']);

    $this->post('/settings/two-factor/recovery-codes', ['code' => 'aaaaa-bbbbb'])->assertSessionHasNoErrors();
    expect($this->admin->refresh()->recoveryCodes())->not->toContain('AAAAA-BBBBB')
        ->and($this->admin->hasTwoFactorEnabled())->toBeTrue();
});

test('two-factor cannot be weakened while impersonating', function () {
    [$secret] = hardenTwoFactor($this->admin);

    $this->actingAs($this->admin)->withSession(['impersonator_id' => 999])
        ->delete('/settings/two-factor', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])
        ->assertForbidden();
    expect($this->admin->refresh()->hasTwoFactorEnabled())->toBeTrue();
});

test('cancelling a half-finished two-factor setup needs no code', function () {
    $this->actingAs($this->admin)->post('/settings/two-factor');
    expect($this->admin->refresh()->two_factor_secret)->not->toBeNull();

    $this->delete('/settings/two-factor')->assertSessionHasNoErrors();
    expect($this->admin->refresh()->two_factor_secret)->toBeNull();
});

test('staff need their password to change their sign-in email', function () {
    $old = $this->admin->email;
    $payload = ['first_name' => 'Pat', 'last_name' => 'Lee', 'email' => 'new@example.com'];

    $this->actingAs($this->admin)->patch('/settings/profile', $payload)->assertSessionHasErrors('current_password');
    $this->patch('/settings/profile', $payload + ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
    expect($this->admin->refresh()->email)->toBe($old);

    $this->patch('/settings/profile', $payload + ['current_password' => 'password'])->assertSessionHasNoErrors();
    expect($this->admin->refresh()->email)->toBe('new@example.com');
});

test('staff can still change their name without a password', function () {
    $this->actingAs($this->admin)
        ->patch('/settings/profile', ['first_name' => 'Renamed', 'last_name' => 'Lee', 'email' => $this->admin->email])
        ->assertSessionHasNoErrors();

    expect($this->admin->refresh()->first_name)->toBe('Renamed');
});

test('login is throttled per ip whatever email is tried', function () {
    foreach (range(1, 20) as $i) {
        $this->post('/login', ['email' => "nobody{$i}@example.com", 'password' => 'x'])->assertSessionHasErrors('email');
    }

    $this->post('/login', ['email' => 'nobody21@example.com', 'password' => 'x'])->assertTooManyRequests();
});

test('insights counts only this company\'s techs', function () {
    User::factory()->agent()->for($this->tenant)->create();
    $other = Tenant::factory()->create();
    User::factory()->agent()->count(3)->for($other)->create();

    $this->actingAs($this->admin)->get('/insights')
        ->assertInertia(fn ($page) => $page->where('active_agents', 1));
});

test('the invoice export guards formula cells', function () {
    $customer = Customer::factory()->for($this->tenant)->create(['first_name' => '=HYPERLINK("http://x")', 'last_name' => 'Evil']);
    Invoice::create(['customer_id' => $customer->id, 'number' => 'INV-00001', 'status' => 'sent', 'subtotal' => 50, 'tax' => 0, 'total' => 50, 'amount_paid' => 0]);

    $csv = $this->actingAs($this->admin)->get('/balances/export')->streamedContent();

    expect($csv)->toContain("'=HYPERLINK")->not->toContain(',=HYPERLINK');
});

test('csv cells starting with a formula character are quoted', function (string $in, string $out) {
    expect(Csv::cell($in))->toBe($out);
})->with([
    ['=1+1', "'=1+1"], ['+1', "'+1"], ['-1', "'-1"], ['@x', "'@x"], ['Plain', 'Plain'], ['', ''],
]);

test('the subscription webhook refuses calls when no secret is configured', function () {
    expect(config('cashier.webhook.secret'))->not->toBeEmpty();

    $this->postJson('/cashier/webhook', ['type' => 'customer.subscription.deleted', 'data' => ['object' => []]])
        ->assertForbidden();
});

/** A subscription webhook call signed the way Stripe signs them. */
function signedSubscriptionWebhook(string $secret): TestResponse
{
    $payload = (string) json_encode(['id' => 'evt_test', 'type' => 'charge.refunded', 'data' => ['object' => []]]);
    $time = time();

    return test()->call('POST', '/cashier/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => "t={$time},v1=".hash_hmac('sha256', "{$time}.{$payload}", $secret),
        'CONTENT_TYPE' => 'application/json',
    ], $payload);
}

test('the subscription webhook takes a call signed with its own secret, and no other', function () {
    config(['cashier.webhook.secret' => 'whsec_subscriptions', 'services.stripe.webhook_secret' => 'whsec_customer_payments']);

    signedSubscriptionWebhook('whsec_subscriptions')->assertOk();
    // The customer-payments secret must not open the subscription endpoint.
    signedSubscriptionWebhook('whsec_customer_payments')->assertForbidden();
});

test('a two-factor code works once', function () {
    [$secret] = hardenTwoFactor($this->admin);
    $code = app(Google2FA::class)->getCurrentOtp($secret);

    $this->post('/login', ['email' => $this->admin->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect(route('dashboard', absolute: false));
    $this->post('/logout');

    // Someone who watched it typed tries the same code inside its 30 seconds.
    $this->post('/login', ['email' => $this->admin->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
});

test('a password an admin sets for someone else meets the same rule as any other', function () {
    $customer = Customer::factory()->for($this->tenant)->create();
    $tech = ['first_name' => 'Tess', 'email' => 'tess@example.com'];

    $this->actingAs($this->admin)->post('/agents', $tech + ['password' => 'ninechars'])->assertSessionHasErrors('password');
    $this->actingAs($this->admin)->post("/customers/{$customer->id}/portal", ['password' => 'ninechars'])->assertSessionHasErrors('password');

    $this->actingAs($this->admin)->post('/agents', $tech + ['password' => 'ten-chars!'])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post("/customers/{$customer->id}/portal", ['password' => 'ten-chars!'])->assertSessionHasNoErrors();
});

test('a picture too large to unpack safely is refused', function () {
    $this->actingAs($this->admin)
        ->patch('/settings/profile', [
            'first_name' => 'Ada', 'email' => $this->admin->email,
            'photo' => UploadedFile::fake()->image('wide.png', 12001, 4),
        ])
        ->assertSessionHasErrors('photo');
});
