<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RegisterTenant;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super-admin platform console — tenant management. Super admins carry no
 * tenant context, so the global TenantScope is inert here and these queries
 * legitimately span every tenant.
 */
class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeSuper($request);

        $tenants = Tenant::query()
            ->withCount(['users', 'pools'])
            ->latest()
            ->get()
            ->map(fn (Tenant $t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'logo_url' => $this->photoUrl($t->getAttribute('logo_path')),
                'slug' => $t->slug,
                'status' => $t->getAttribute('status'),
                'users' => $t->getAttribute('users_count'),
                'pools' => $t->getAttribute('pools_count'),
                'created' => $t->created_at?->toDateString(),
            ])->all();

        return Inertia::render('admin/Tenants', [
            'tenants' => $tenants,
        ]);
    }

    public function store(StoreTenantRequest $request, RegisterTenant $register): RedirectResponse
    {
        // RegisterTenant builds the tenant + its first admin atomically; a
        // console-created admin is pre-verified so they can sign in immediately.
        $admin = $register($request->validated());
        $admin->forceFill(['email_verified_at' => now()])->save();

        return back()->with('success', 'Tenant created.');
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update($request->validated());

        return back()->with('success', 'Tenant updated.');
    }

    private function authorizeSuper(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin() === true, 403);
    }

    /** Public URL for a stored photo path, or null when unset. */
    private function photoUrl(mixed $path): ?string
    {
        return is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;
    }
}
