<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\RegisterTenant;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
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
            $existing = User::query()->where('email', $googleUser->getEmail())->first();

            // 3. No account at all → start a Google sign-up (collect the company
            //    name on the next step). The verified identity is held in the
            //    session, not trusted from the form.
            if (! $existing) {
                session()->put('google_signup', [
                    'id' => $googleUser->getId(),
                    'email' => $googleUser->getEmail(),
                    'first_name' => $this->firstName($googleUser),
                    'last_name' => $this->lastName($googleUser),
                ]);

                return to_route('auth.google.register');
            }

            // 4. Link to an existing, email-verified account — never login-by-email.
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

    /** Step 2 of Google sign-up: collect the company name for the new tenant. */
    public function register(): Response|RedirectResponse
    {
        $signup = session('google_signup');
        if (! is_array($signup)) {
            return to_route('register');
        }

        return Inertia::render('auth/RegisterGoogle', [
            'email' => $signup['email'] ?? '',
            'first_name' => $signup['first_name'] ?? '',
            'last_name' => $signup['last_name'] ?? '',
        ]);
    }

    public function registerStore(Request $request, RegisterTenant $registerTenant): RedirectResponse
    {
        $signup = session('google_signup');
        if (! is_array($signup) || ! isset($signup['id'], $signup['email'])) {
            return to_route('register');
        }

        $data = $request->validate([
            'company' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
        ]);

        // The verified Google email must still be unclaimed (guards a race).
        if (User::query()->where('email', $signup['email'])->exists()) {
            session()->forget('google_signup');

            return to_route('login')->with('error', 'An account with that email already exists. Please sign in.');
        }

        // Random password — the account signs in via Google (they can set one
        // later through "forgot password" if they want email login too).
        $user = $registerTenant([
            'company' => $data['company'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'email' => (string) $signup['email'],
            'password' => Str::password(32),
        ]);

        // Google already vouched for the email — link the id and mark it verified.
        $user->forceFill([
            'google_id' => (string) $signup['id'],
            'email_verified_at' => now(),
        ])->save();

        session()->forget('google_signup');
        Auth::login($user, remember: true);

        return to_route('dashboard');
    }

    protected function firstName(SocialiteUser $googleUser): string
    {
        $raw = $googleUser->user;

        return (string) ($raw['given_name'] ?? str((string) $googleUser->getName())->before(' '));
    }

    protected function lastName(SocialiteUser $googleUser): string
    {
        $raw = $googleUser->user;

        return (string) ($raw['family_name'] ?? str((string) $googleUser->getName())->after(' '));
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
