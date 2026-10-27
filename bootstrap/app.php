<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureSingleSession;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Routing\Middleware\SubstituteBindings;
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

        // The company must be known BEFORE route-model binding runs: TenantScope
        // filters a {customer}/{pool}/… lookup by the bound tenant, and with no
        // tenant bound yet it filters nothing — another company's id would resolve.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: ResolveTenant::class);

        // Route-layer role backstop (mirrors controllers' inline authorize* guards).
        $middleware->alias(['role' => EnsureRole::class]);

        // Stripe posts webhooks without a CSRF token (verified by signature).
        // Inboxes post one-click unsubscribes with no CSRF token; the signed URL is the proof.
        $middleware->validateCsrfTokens(except: ['stripe/webhook', 'email-preferences/*/one-click/*', 'report-copies/*/one-click']);

        // The sidebar collapse state is a plaintext cookie set client-side and
        // read back at render time to avoid an open→collapse flash — leave it
        // unencrypted so the server can read the value the browser wrote.
        $middleware->encryptCookies(except: ['sidebar_state']);
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
