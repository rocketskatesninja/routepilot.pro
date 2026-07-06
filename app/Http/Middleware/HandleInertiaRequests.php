<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\BillingMeter;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * Shares the authenticated user, the resolved tenant (branding/timezone),
     * the user's granular Spatie permissions, and one-shot flash messages.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $tenant = app()->has('tenant') ? app('tenant') : null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            // Persisted sidebar collapse state — read from the cookie the client
            // writes on toggle so the very first render matches (no flash). Absent
            // cookie = expanded.
            'sidebarOpen' => $request->cookie('sidebar_state') !== 'false',
            // For plain <form> POSTs that leave the app (e.g. Stripe Checkout),
            // where Inertia's XHR/header CSRF doesn't apply.
            'csrf' => csrf_token(),
            'auth' => [
                'user' => $user,
                'role' => $user?->role,
                'permissions' => $user
                    ? $user->getAllPermissions()->pluck('name')->values()
                    : [],
                'impersonating' => $request->session()->has('impersonator_id'),
                'unread' => $user !== null ? $user->unreadNotifications()->count() : 0,
            ],
            // Lazy: the public /t/{slug} route binds its tenant inside the controller,
            // which runs AFTER share() — so resolve app('tenant') at prop-resolution
            // time (else the public landing loses its brand name / logo / colour).
            'tenant' => function () {
                $t = app()->has('tenant') ? app('tenant') : null;

                return $t instanceof Tenant ? [
                    'id' => $t->id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'brand_color' => $t->brand_color,
                    'logo_path' => $t->logo_path,
                    'timezone' => $t->timezone,
                ] : null;
            },
            // Platform-billing state (trial/subscription) + metered usage for the
            // signed-in tenant — drives the trial banner + billing screen.
            'billing' => ($user !== null && $tenant instanceof Tenant) ? [
                ...$tenant->billingState(),
                'usage' => app(BillingMeter::class)->for($tenant),
            ] : null,
            // Ziggy route table — so route() works during SSR (Node has no
            // @routes browser global). Lazy: only built on full page loads.
            'ziggy' => fn (): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
