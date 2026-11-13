<?php

declare(strict_types=1);

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

// An idle tab's session runs out, so its next POST fails the CSRF check (419).
// The check itself is skipped under tests, so a route raises the same exception.
beforeEach(function () {
    Route::middleware('web')->post('/_test/expired', fn () => throw new TokenMismatchException('CSRF token mismatch.'));
});

test('a page’s own fetch gets a plain 419 that says it was signed out', function () {
    // postJson sends Accept: application/json, like the assistant's send.
    $this->postJson('/_test/expired')
        ->assertStatus(419)
        ->assertExactJson(['error' => 'You’ve been signed out. Sign in again, then try it.']);
});

test('a normal form post still goes back with the session-expired notice', function () {
    $this->from('/settings/profile')
        ->post('/_test/expired')
        ->assertRedirect('/settings/profile')
        ->assertSessionHas('error', 'Your session expired — please sign in again.');
});

test('an Inertia visit is not treated as a fetch: it still goes back', function () {
    // Inertia's Accept header names HTML, so the redirect (→ login) still applies.
    $this->from('/assistant')
        ->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html, application/xhtml+xml'])
        ->post('/_test/expired')
        ->assertRedirect('/assistant');
});
