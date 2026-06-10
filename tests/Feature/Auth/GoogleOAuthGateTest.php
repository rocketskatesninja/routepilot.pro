<?php

use App\Models\Tenant;
use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Build a fake Google identity and wire it through the Socialite facade.
 */
function fakeGoogleUser(string $id, string $email, bool $verified): void
{
    $google = new SocialiteUser;
    $google->id = $id;
    $google->email = $email;
    $google->user = ['email_verified' => $verified];

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
