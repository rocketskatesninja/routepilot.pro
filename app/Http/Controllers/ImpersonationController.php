<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
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
    public function start(Request $request, Tenant $tenant): RedirectResponse
    {
        $super = $request->user();
        abort_unless($super?->isSuperAdmin() === true, 403);

        $admin = User::query()
            ->where('tenant_id', $tenant->id)->where('role', 'tenant_admin')->where('is_active', true)
            ->first();
        abort_if($admin === null, 404, 'This tenant has no active admin to impersonate.');

        AuditLog::record($super, 'impersonate.start', $admin);

        $request->session()->put('impersonator_id', $super->id);
        Auth::login($admin);

        return redirect('/dashboard');
    }

    public function stop(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->pull('impersonator_id');
        if ($impersonatorId === null) {
            return redirect('/dashboard');
        }

        $super = User::find($impersonatorId);
        if ($super !== null) {
            AuditLog::record($super, 'impersonate.stop', $request->user());
            Auth::login($super);
        }

        return redirect('/people');
    }
}
