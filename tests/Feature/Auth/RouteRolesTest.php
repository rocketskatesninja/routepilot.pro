<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/**
 * Every route is either behind a `role:` rule, or named here on purpose. A new
 * route added outside a role group fails this test until someone decides which
 * list it belongs on, so a page can't be left open by accident.
 */

/** Open to anyone: sign-in pages, public pages, signed email links, webhooks (which check their own signature). */
const OPEN_ROUTES = [
    '/', 'up', 'privacy', 'terms', 'storage/{path}',
    'login', 'register', 'forgot-password', 'reset-password', 'reset-password/{token}', 'two-factor-challenge',
    'auth/google/redirect', 'auth/google/callback', 'auth/google/register',
    't/{tenant}', 'public/{tenant}/chat', 'public/{tenant}/leads',
    'pay/{customer}', 'pay/thanks',
    'email-preferences/{customer}', 'email-preferences/{customer}/one-click/{type}', 'unsubscribe/{customer}',
    'report-copies/{pool}', 'report-copies/{pool}/one-click', 'report-message/{visit}',
    'settings/email/confirm',
    'stripe/webhook', 'cashier/webhook', 'cashier/payment/{id}',
    'broadcasting/auth', // answers 403 itself unless signed in and allowed on the channel
];

/** For anyone signed in, whatever their role; each controller narrows what that person sees. */
const SIGNED_IN_ROUTES = [
    'dashboard', 'dashboard/layout', 'paused', 'logout', 'impersonate/stop',
    'assistant', 'assistant/new', 'assistant/send',
    'notifications', 'notifications/bulk', 'notifications/read-all', 'notifications/types',
    'notifications/{id}', 'notifications/{id}/open', 'notifications/{id}/read', 'notifications/{id}/unread',
    'push-subscriptions', 'invoices/{invoice}/pdf',
    'confirm-password', 'verify-email', 'verify-email/{id}/{hash}', 'email/verification-notification',
    'settings', 'settings/appearance', 'settings/password', 'settings/profile',
    'settings/two-factor', 'settings/two-factor/confirm', 'settings/two-factor/recovery-codes',
];

/** @return array<string, list<string>> uri => middleware, for routes with no role rule */
function routesWithoutRole(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->reject(fn (RoutingRoute $r): bool => collect($r->gatherMiddleware())->contains(fn ($m): bool => is_string($m) && str_starts_with($m, 'role:')))
        ->mapWithKeys(fn (RoutingRoute $r): array => [$r->uri() => array_values(array_filter($r->gatherMiddleware(), 'is_string'))])
        ->all();
}

test('every route has a role rule or is listed as open or any-role', function () {
    $unlisted = array_diff(array_keys(routesWithoutRole()), OPEN_ROUTES, SIGNED_IN_ROUTES);

    expect(array_values($unlisted))->toBe([]);
});

test('the any-role routes all require signing in', function () {
    $routes = routesWithoutRole();

    foreach (SIGNED_IN_ROUTES as $uri) {
        expect($routes)->toHaveKey($uri);
        expect($routes[$uri])->toContain('auth');
    }
});

test('the open list holds nothing that has since gone or moved behind a role', function () {
    expect(array_values(array_diff(OPEN_ROUTES, array_keys(routesWithoutRole()))))->toBe([]);
});
