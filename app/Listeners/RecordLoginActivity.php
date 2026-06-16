<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Record a successful authentication: stamp `last_login_at` and append an
 * `auth.login` audit row. Covers every login path (password, Google OAuth)
 * since they all fire the Login event.
 *
 * Skips impersonation — it has its own audit trail (ImpersonationController)
 * and must not move the impersonated user's last_login_at while a super-admin
 * is acting as them.
 */
class RecordLoginActivity
{
    public function handle(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }

        if (session()->has('impersonator_id')) {
            return;
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        // The login request never resolved a tenant (the user wasn't
        // authenticated when ResolveTenant ran), so bind it now to scope the
        // audit row to the staff member's company.
        if ($user->tenant_id !== null && ! app()->has('tenant_id')) {
            app()->instance('tenant_id', $user->tenant_id);
        }

        AuditLog::record($user, 'auth.login', $user);
    }
}
