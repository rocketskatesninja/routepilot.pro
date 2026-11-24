<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\TwoFactorAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff TOTP two-factor enrollment. The secret/recovery codes are encrypted at
 * rest and never $fillable — set via forceFill here. Enabling is a two-step
 * commit: store an unconfirmed secret, then confirm a code from the app.
 */
class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorAuth $twoFactor) {}

    public function show(Request $request): Response
    {
        $user = $this->staff($request);
        $pending = $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null;
        $flashCodes = $request->session()->get('two_factor_recovery_codes');

        return Inertia::render('settings/TwoFactor', [
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending' => $pending,
            'qrSvg' => $pending ? $this->twoFactor->qrSvg($user, (string) $user->two_factor_secret) : null,
            'setupKey' => $pending ? (string) $user->two_factor_secret : null,
            'recoveryCodes' => $pending ? $user->recoveryCodes() : (is_array($flashCodes) ? $flashCodes : null),
        ]);
    }

    /** Begin enrollment: generate an unconfirmed secret + recovery codes. */
    public function store(Request $request): RedirectResponse
    {
        $user = $this->staff($request);
        if ($user->hasTwoFactorEnabled()) {
            return back();
        }

        $user->forceFill([
            'two_factor_secret' => $this->twoFactor->newSecret(),
            'two_factor_recovery_codes' => json_encode($this->twoFactor->newRecoveryCodes()),
            'two_factor_confirmed_at' => null,
        ])->save();

        return back();
    }

    /** Finish enrollment: verify a code from the authenticator app. */
    public function confirm(Request $request): RedirectResponse
    {
        $user = $this->staff($request);
        $validated = $request->validate(['code' => ['required', 'string']]);

        $secret = $user->two_factor_secret;
        if (! is_string($secret) || ! $this->twoFactor->verify($secret, $validated['code'])) {
            return back()->withErrors(['code' => 'That code is invalid. Try the current code from your app.']);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        AuditLog::record($user, 'two_factor.enabled', $user);

        return back()->with('success', 'Two-factor authentication is on.');
    }

    /** Regenerate recovery codes (shown once via flash). Needs a current code. */
    public function recoveryCodes(Request $request): RedirectResponse
    {
        $user = $this->staff($request);
        if (! $user->hasTwoFactorEnabled()) {
            return back();
        }
        $this->confirmCode($request, $user);

        $codes = $this->twoFactor->newRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => json_encode($codes)])->save();
        AuditLog::record($user, 'two_factor.recovery_codes_regenerated', $user);

        return back()->with('two_factor_recovery_codes', $codes);
    }

    /**
     * Disable 2FA and clear all secrets. Turning off a confirmed 2FA needs a
     * current code; cancelling a half-finished setup does not.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $this->staff($request);
        if ($user->hasTwoFactorEnabled()) {
            $this->confirmCode($request, $user);
        }
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        AuditLog::record($user, 'two_factor.disabled', $user);

        return back()->with('success', 'Two-factor authentication is off.');
    }

    /** 2FA is staff-only — customers may not enroll. */
    private function staff(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isStaff(), 403);

        return $user;
    }

    /**
     * A signed-in session alone must not be enough to weaken 2FA (an unlocked
     * laptop, or the platform owner signed in as this person): require a code
     * from the app, or a recovery code, which is then used up.
     */
    private function confirmCode(Request $request, User $user): void
    {
        abort_if($request->session()->has('impersonator_id'), 403, 'Not available while signed in as someone else.');

        $code = trim((string) $request->input('code'));
        if ($code !== '' && $this->twoFactor->verify((string) $user->two_factor_secret, $code)) {
            return;
        }

        $codes = $user->recoveryCodes();
        $index = array_search(strtoupper($code), array_map('strtoupper', $codes), true);
        if ($code !== '' && $index !== false) {
            unset($codes[$index]);
            $user->forceFill(['two_factor_recovery_codes' => json_encode(array_values($codes))])->save();

            return;
        }

        throw ValidationException::withMessages(['code' => 'Enter a current code from your app, or a recovery code.']);
    }
}
