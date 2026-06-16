<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;

test('a deactivated account cannot authenticate even with the correct password', function () {
    $user = User::factory()->create();
    $user->forceFill(['is_active' => false])->save();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(AuditLog::query()->where('action', 'auth.blocked_inactive')->where('user_id', $user->id)->count())->toBe(1);
});

test('a successful login stamps last_login_at and writes an audit row', function () {
    $user = User::factory()->create();
    expect($user->last_login_at)->toBeNull();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'auth.login')->where('user_id', $user->id)->count())->toBe(1);
});

test('a failed login is audited and never authenticates', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(AuditLog::query()->where('action', 'auth.failed')->count())->toBe(1);
});

test('repeated failures lock out and audit the lockout', function () {
    $user = User::factory()->create();

    // Five failures fill the limiter; the sixth attempt is locked out.
    foreach (range(1, 6) as $ignored) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
    }

    $this->assertGuest();
    expect(AuditLog::query()->where('action', 'auth.lockout')->count())->toBeGreaterThanOrEqual(1);
});
