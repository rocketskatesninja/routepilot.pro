<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce one live session per user (per-seat billing → no account sharing).
 * Each login rotates the user's session_token and stamps the same token into
 * the session; a session whose token no longer matches was superseded by a
 * newer login, so we sign it out. Impersonation is exempt (it rides the
 * super-admin's session and is separately audited).
 */
class EnsureSingleSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User
            && ! $request->session()->has('impersonator_id')
            && is_string($user->session_token) && $user->session_token !== ''
            && $request->session()->get('auth_token') !== $user->session_token) {

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(401, 'Signed out — this account was signed in on another device.');
            }

            return redirect()->route('login')
                ->with('status', 'You were signed out because this account was signed in on another device.');
        }

        return $next($request);
    }
}
