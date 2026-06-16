<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\TwoFactorAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The second login step for a 2FA-enabled user. The first step (LoginRequest)
 * verified the password and parked the user id in the session WITHOUT logging
 * them in; here we verify a TOTP code or a single-use recovery code, then
 * complete the login.
 */
class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/TwoFactorChallenge');
    }

    public function store(Request $request, TwoFactorAuth $twoFactor): RedirectResponse
    {
        $user = $this->pendingUser($request);
        $code = trim((string) $request->input('code'));

        if ($code === '') {
            return back()->withErrors(['code' => 'Enter the code from your app or a recovery code.']);
        }

        // TOTP code.
        if ($twoFactor->verify((string) $user->two_factor_secret, $code)) {
            return $this->complete($request, $user, 'two_factor.challenge_passed');
        }

        // Single-use recovery code.
        $codes = $user->recoveryCodes();
        $index = array_search(strtoupper($code), array_map('strtoupper', $codes), true);
        if ($index !== false) {
            unset($codes[$index]);
            $user->forceFill(['two_factor_recovery_codes' => json_encode(array_values($codes))])->save();

            return $this->complete($request, $user, 'two_factor.recovery_used');
        }

        AuditLog::record($user, 'two_factor.challenge_failed', $user);

        return back()->withErrors(['code' => 'That code is invalid.']);
    }

    /** The user parked by the password step — 403 if the session is missing/stale. */
    private function pendingUser(Request $request): User
    {
        $id = $request->session()->get('login.id');
        abort_unless(is_int($id) || (is_string($id) && ctype_digit($id)), 403);

        $user = User::find((int) $id);
        abort_unless($user instanceof User && $user->hasTwoFactorEnabled(), 403);

        return $user;
    }

    private function complete(Request $request, User $user, string $action): RedirectResponse
    {
        $remember = (bool) $request->session()->pull('login.remember', false);
        $request->session()->forget('login.id');

        Auth::login($user, $remember);
        $request->session()->regenerate();
        AuditLog::record($user, $action, $user);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
