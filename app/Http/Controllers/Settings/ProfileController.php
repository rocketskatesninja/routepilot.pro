<?php

namespace App\Http\Controllers\Settings;

use App\Actions\ConfirmEmailChange;
use App\Actions\SendEmailChangeVerification;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use App\Services\PhotoService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'pendingEmail' => $request->session()->get('pendingEmail'),
            'canDeleteAccount' => $this->canSelfDelete($request),
        ]);
    }

    /** Agents and the platform super-admin cannot self-delete their account. */
    private function canSelfDelete(Request $request): bool
    {
        return ! in_array($request->user()?->role, ['agent', 'super_admin'], true);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, PhotoService $photos, SendEmailChangeVerification $verifier): RedirectResponse
    {
        $user = $request->user();
        $newEmail = strtolower(trim((string) $request->validated('email')));
        $emailChanged = $newEmail !== $user->email;

        // A customer's login and contact email are one and the same, so a change
        // only lands after they confirm it from the new inbox. Staff keep the
        // immediate change (they have no contact-record duality).
        $needsVerification = $emailChanged && $user->role === 'customer';

        $user->fill($request->safe()->only(['first_name', 'last_name']));
        if ($emailChanged && ! $needsVerification) {
            $user->forceFill(['email' => $newEmail, 'email_verified_at' => null]);
        }

        $photo = $request->file('photo');
        if ($photo instanceof UploadedFile) {
            $old = $user->getAttribute('avatar_path');
            $user->forceFill(['avatar_path' => $photos->replace($photo, is_string($old) ? $old : null, 'avatars')]);
        }

        $user->save();

        if ($needsVerification) {
            $verifier->handle($user, $newEmail);

            return to_route('profile.edit')
                ->with('status', 'email-change-sent')
                ->with('pendingEmail', $newEmail);
        }

        return to_route('profile.edit');
    }

    /**
     * Land a customer's verified email change (signed link opened from the new
     * inbox). Reachable while signed out, so redirect accordingly.
     */
    public function confirmEmailChange(Request $request, ConfirmEmailChange $action): RedirectResponse
    {
        $user = User::find($request->integer('user'));
        $newEmail = strtolower(trim((string) $request->query('email')));

        if ($user === null || $newEmail === '') {
            return redirect('/login')->with('status', 'That email confirmation link is no longer valid.');
        }

        $status = $action->handle($user, $newEmail)
            ? 'Your email address has been updated.'
            : 'That email address is already in use, so the change was not applied.';

        return Auth::check() && Auth::id() === $user->id
            ? to_route('profile.edit')->with('status', $status)
            : redirect('/login')->with('status', $status);
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(Request $request): RedirectResponse
    {
        abort_unless($this->canSelfDelete($request), 403, 'This account type cannot be self-deleted.');

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
