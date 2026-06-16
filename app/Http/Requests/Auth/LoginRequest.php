<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /** Set when the password was correct but the user must still pass a 2FA challenge. */
    public bool $twoFactorPending = false;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // Validate credentials WITHOUT logging in, so a deactivated account is
        // never granted a session and the Login event only fires for an
        // allowed sign-in.
        if (! Auth::validate($this->only('email', 'password'))) {
            RateLimiter::hit($this->throttleKey());
            AuditLog::record(null, 'auth.failed', null, ['email' => (string) $this->string('email')]);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $user = Auth::getLastAttempted();

        if ($user instanceof User && ! $user->is_active) {
            RateLimiter::hit($this->throttleKey());
            $this->scopeTenant($user);
            AuditLog::record($user, 'auth.blocked_inactive', $user);

            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated. Please contact your administrator.',
            ]);
        }

        // Password is correct — but a 2FA-enabled user isn't logged in yet.
        // Park them for the challenge step (TwoFactorChallengeController).
        if ($user instanceof User && $user->hasTwoFactorEnabled()) {
            $this->session()->put('login.id', $user->getKey());
            $this->session()->put('login.remember', $this->boolean('remember'));
            RateLimiter::clear($this->throttleKey());
            $this->twoFactorPending = true;

            return;
        }

        Auth::login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Bind the user's tenant so a pre-authentication audit row (deactivated
     * login) is scoped to their company — the login request hasn't resolved a
     * tenant yet.
     */
    private function scopeTenant(User $user): void
    {
        if ($user->tenant_id !== null && ! app()->has('tenant_id')) {
            app()->instance('tenant_id', $user->tenant_id);
        }
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));
        AuditLog::record(null, 'auth.lockout', null, ['email' => (string) $this->string('email')]);

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->string('email')).'|'.$this->ip());
    }
}
