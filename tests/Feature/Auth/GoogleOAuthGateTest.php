<?php

use App\Models\Tenant;
use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Build a fake Google identity and wire it through the Socialite facade.
 */
function fakeGoogleUser(string $id, string $email, bool $verified, ?array $names = null): void
{
    $google = new SocialiteUser;
    $google->id = $id;
    $google->email = $email;
    $google->user = array_merge(['email_verified' => $verified], $names ?? []);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($google);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

test('unverified google email is rejected', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->for($tenant)->create([
        'email' => 'owner@example.com',
        'google_id' => null,
    ]);

    fakeGoogleUser('g-1', 'owner@example.com', verified: false);

    $this->get(route('auth.google.callback'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('verified email without a linked google_id does not log in by email alone', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->for($tenant)->create([
        'email' => 'owner@example.com',
        'google_id' => null,
        'email_verified_at' => null, // not yet verified locally
    ]);

    fakeGoogleUser('g-2', 'owner@example.com', verified: true);

    $this->get(route('auth.google.callback'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('login succeeds only via a previously linked google_id', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create([
        'email' => 'owner@example.com',
    ]);
    $user->forceFill(['google_id' => 'g-3'])->save();

    fakeGoogleUser('g-3', 'owner@example.com', verified: true);

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('an unknown verified google email starts sign-up (no account is created yet)', function () {
    fakeGoogleUser('g-new', 'new@pool.test', verified: true, names: ['given_name' => 'Jane', 'family_name' => 'Doe']);

    $this->get(route('auth.google.callback'))->assertRedirect(route('auth.google.register'));
    $this->assertGuest();
    expect(session('google_signup'))->toMatchArray(['id' => 'g-new', 'email' => 'new@pool.test', 'first_name' => 'Jane', 'last_name' => 'Doe']);
    expect(User::query()->where('email', 'new@pool.test')->exists())->toBeFalse();
});

test('completing google sign-up creates a verified, linked tenant admin and logs in', function () {
    session(['google_signup' => ['id' => 'g-new', 'email' => 'new@pool.test', 'first_name' => 'Jane', 'last_name' => 'Doe']]);

    $this->post(route('auth.google.register.store'), ['company' => 'Blue Wave Pools', 'first_name' => 'Jane', 'last_name' => 'Doe'])
        ->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'new@pool.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe('tenant_admin')
        ->and($user->google_id)->toBe('g-new')
        ->and($user->email_verified_at)->not->toBeNull();
    expect(Tenant::query()->where('name', 'Blue Wave Pools')->exists())->toBeTrue();
    $this->assertAuthenticatedAs($user);
    expect(session('google_signup'))->toBeNull();
});

test('google sign-up requires a company name', function () {
    session(['google_signup' => ['id' => 'g-new', 'email' => 'new@pool.test', 'first_name' => 'Jane', 'last_name' => 'Doe']]);

    $this->post(route('auth.google.register.store'), ['company' => '', 'first_name' => 'Jane'])->assertSessionHasErrors('company');
    $this->assertGuest();
    expect(User::query()->where('email', 'new@pool.test')->exists())->toBeFalse();
});

test('the google sign-up step requires a pending google identity in the session', function () {
    $this->get(route('auth.google.register'))->assertRedirect(route('register'));
    $this->post(route('auth.google.register.store'), ['company' => 'X', 'first_name' => 'Y'])->assertRedirect(route('register'));
});
