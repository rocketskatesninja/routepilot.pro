<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Audited super-admin impersonation. The original super-admin id is parked in
 * the session so it can be restored; both start and stop are written to the
 * audit log.
 */
class ImpersonationController extends Controller
{
    /** Sign in as a company: its (first active) admin. */
    public function start(Request $request, Tenant $tenant): RedirectResponse
    {
        $admin = User::query()
            ->where('tenant_id', $tenant->id)->where('role', 'tenant_admin')->where('is_active', true)
            ->first();
        abort_if($admin === null, 404, 'This tenant has no active admin to impersonate.');

        return $this->begin($request, $admin);
    }

    /** Sign in as one person: a company admin, an agent, or a customer's portal login. */
    public function user(Request $request, User $user): RedirectResponse
    {
        // Tenant users only — never another super-admin.
        abort_unless(in_array($user->role, ['tenant_admin', 'agent', 'customer'], true) && $user->tenant_id !== null, 403);

        return $this->begin($request, $user);
    }

    public function stop(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->pull('impersonator_id');
        if ($impersonatorId === null) {
            return redirect('/dashboard');
        }

        // Back to the person you were looking at (resolved while their tenant is still bound).
        $back = $this->peopleRow($request->user());

        $super = User::find($impersonatorId);
        if ($super === null) {
            // The super-admin account is gone: don't leave this session signed in
            // as the other person with no banner and no way back.
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        AuditLog::record($super, 'impersonate.stop', $request->user());
        Auth::login($super);

        return redirect($back);
    }

    private function begin(Request $request, User $target): RedirectResponse
    {
        $super = $request->user();
        abort_unless($super?->isSuperAdmin() === true, 403);

        AuditLog::record($super, 'impersonate.start', $target);

        $request->session()->put('impersonator_id', $super->id);
        Auth::login($target);

        return redirect('/dashboard');
    }

    /** The impersonated person's row on the platform People screen. */
    private function peopleRow(?User $user): string
    {
        $query = match ($user?->role) {
            'tenant_admin' => ['type' => 'tenants', 'selected' => $user->tenant_id, 'selected_type' => 'tenant'],
            'agent' => ['type' => 'agents', 'selected' => $user->id, 'selected_type' => 'agent'],
            'customer' => ['type' => 'customers', 'selected' => Customer::query()->where('user_id', $user->id)->value('id'), 'selected_type' => 'customer'],
            default => [],
        };

        return $query !== [] && $query['selected'] !== null ? '/people?'.http_build_query($query) : '/people';
    }
}
