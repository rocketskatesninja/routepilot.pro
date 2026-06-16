<?php

use App\Http\Middleware\EnsureSingleSession;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            ResolveTenant::class,
            EnsureSingleSession::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Stripe posts webhooks without a CSRF token (verified by signature).
        $middleware->validateCsrfTokens(except: ['stripe/webhook']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // A 419 (expired CSRF/session) — e.g. signing out from a long-idle tab —
        // should redirect back with a notice, not show the bare "page expired" page.
        $exceptions->respond(function (Response $response): Response {
            if ($response->getStatusCode() === 419) {
                return back()->with('error', 'Your session expired — please sign in again.');
            }

            return $response;
        });
    })->create();
