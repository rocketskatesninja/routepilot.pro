<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * Google OAuth — correct by design (audit fix).
 *
 * The legacy controller logged a user in on an email match alone, which let
 * anyone who controlled an unverified Google account with a victim's email
 * take over the account. Here we:
 *   1. Require Google to report the email as verified.
 *   2. Log in ONLY by a previously linked `google_id`.
 *   3. Link a Google id to an existing account only when that account's
 *      email is already verified — never auto-login by email.
 */
class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        /** @var SocialiteUser $googleUser */
        $googleUser = Socialite::driver('google')->user();

        // 1. Google must vouch for the email.
        if (! $this->emailIsVerified($googleUser)) {
            return $this->fail('Your Google email is not verified.');
        }

        // 2. Log in by linked google_id only.
        $user = User::query()->where('google_id', $googleUser->getId())->first();

        if (! $user) {
            // 3. Link to an existing, email-verified account — never login-by-email.
            $existing = User::query()->where('email', $googleUser->getEmail())->first();

            if (! $existing) {
                return $this->fail('No account found for that Google email. Please register first.');
            }

            if (is_null($existing->email_verified_at)) {
                return $this->fail('Please verify your email before linking Google.');
            }

            $existing->forceFill(['google_id' => $googleUser->getId()])->save();
            $user = $existing;
        }

        if (! $user->is_active) {
            return $this->fail('This account is inactive.');
        }

        Auth::login($user, remember: true);

        return to_route('dashboard');
    }

    /**
     * Read Google's `email_verified` claim from the raw Socialite payload.
     */
    protected function emailIsVerified(SocialiteUser $googleUser): bool
    {
        $raw = $googleUser->user;

        return (bool) ($raw['email_verified'] ?? $raw['verified_email'] ?? false);
    }

    protected function fail(string $message): RedirectResponse
    {
        return to_route('login')->with('error', $message);
    }
}
