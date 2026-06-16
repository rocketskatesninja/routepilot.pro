<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->google = app(Google2FA::class);
});

/** A staff user with 2FA fully enabled; returns [user, secret, recoveryCodes]. */
function enrolledStaff(Tenant $tenant, Google2FA $g): array
{
    $secret = $g->generateSecretKey();
    $codes = ['AAAAA-BBBBB', 'CCCCC-DDDDD'];
    $user = User::factory()->for($tenant)->create();
    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => json_encode($codes),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return [$user, $secret, $codes];
}

test('staff can enable and confirm two-factor', function () {
    $admin = User::factory()->for($this->tenant)->create();

    $this->actingAs($admin)->post('/settings/two-factor')->assertRedirect();

    $admin->refresh();
    expect($admin->two_factor_secret)->not->toBeNull()
        ->and($admin->two_factor_confirmed_at)->toBeNull()
        ->and($admin->recoveryCodes())->toHaveCount(8);

    $code = $this->google->getCurrentOtp((string) $admin->two_factor_secret);
    $this->actingAs($admin)->post('/settings/two-factor/confirm', ['code' => $code])->assertRedirect()->assertSessionHasNoErrors();

    expect($admin->fresh()?->hasTwoFactorEnabled())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'two_factor.enabled')->count())->toBe(1);
});

test('a wrong confirmation code is rejected', function () {
    $admin = User::factory()->for($this->tenant)->create();
    $this->actingAs($admin)->post('/settings/two-factor');

    $this->actingAs($admin)->post('/settings/two-factor/confirm', ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect($admin->fresh()?->hasTwoFactorEnabled())->toBeFalse();
});

test('a customer cannot enroll in two-factor', function () {
    $customer = User::factory()->for($this->tenant)->create();
    $customer->forceFill(['role' => 'customer'])->save();

    $this->actingAs($customer)->post('/settings/two-factor')->assertForbidden();
});

test('login with two-factor parks the user at the challenge without logging in', function () {
    [$user] = enrolledStaff($this->tenant, $this->google);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.challenge'));

    $this->assertGuest();
    expect(session('login.id'))->toBe($user->id);
});

test('a valid totp code completes the challenge and logs in', function () {
    [$user, $secret] = enrolledStaff($this->tenant, $this->google);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['code' => $this->google->getCurrentOtp($secret)])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect(AuditLog::query()->where('action', 'two_factor.challenge_passed')->count())->toBe(1);
});

test('a recovery code completes the challenge and is consumed', function () {
    [$user, , $codes] = enrolledStaff($this->tenant, $this->google);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['code' => $codes[0]])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()?->recoveryCodes())->toBe([$codes[1]])
        ->and(AuditLog::query()->where('action', 'two_factor.recovery_used')->count())->toBe(1);
});

test('an invalid challenge code is rejected and audited', function () {
    [$user] = enrolledStaff($this->tenant, $this->google);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->post('/two-factor-challenge', ['code' => '123456'])->assertSessionHasErrors('code');

    $this->assertGuest();
    expect(AuditLog::query()->where('action', 'two_factor.challenge_failed')->count())->toBe(1);
});

test('disabling two-factor clears the secrets', function () {
    [$user] = enrolledStaff($this->tenant, $this->google);

    $this->actingAs($user)->delete('/settings/two-factor')->assertRedirect();

    $user->refresh();
    expect($user->hasTwoFactorEnabled())->toBeFalse()
        ->and($user->two_factor_secret)->toBeNull()
        ->and(AuditLog::query()->where('action', 'two_factor.disabled')->count())->toBe(1);
});
