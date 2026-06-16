<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
});

test('a superseded session is signed out on its next request', function () {
    $user = User::factory()->for($this->tenant)->create();
    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->assertAuthenticated();

    // A fresh login on another device rotates the user's session token.
    $user->forceFill(['session_token' => 'newer-device-token'])->save();

    // Emulate a real next request: drop the guard's cached user so it
    // re-resolves from the DB (each HTTP request does this fresh).
    $this->app['auth']->forgetGuards();

    $this->get('/settings/profile')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('the active single session keeps working', function () {
    $user = User::factory()->for($this->tenant)->create();
    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->get('/settings/profile')->assertOk();
    $this->assertAuthenticated();
});

test('a session predating the feature is not enforced', function () {
    // No real login → no token claim (session_token stays null) → not evicted.
    $user = User::factory()->for($this->tenant)->create();

    $this->actingAs($user)->get('/settings/profile')->assertOk();
    $this->assertAuthenticated();
});

test('each login rotates the token so only the latest session is valid', function () {
    $user = User::factory()->for($this->tenant)->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $first = $user->fresh()?->session_token;

    $this->post('/logout');
    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $second = $user->fresh()?->session_token;

    expect($first)->not->toBeNull()
        ->and($second)->not->toBeNull()
        ->and($second)->not->toBe($first);
});

test('impersonation is exempt from single-session eviction', function () {
    $super = User::factory()->superAdmin()->create();
    $admin = User::factory()->for($this->tenant)->create();

    $this->actingAs($super)->post("/tenants/{$this->tenant->id}/impersonate")
        ->assertSessionHas('impersonator_id', $super->id);

    // A login elsewhere rotates the admin's token, but the impersonating
    // super-admin session must not be evicted.
    $admin->forceFill(['session_token' => 'elsewhere'])->save();

    $this->get('/settings/profile')->assertOk();
});
