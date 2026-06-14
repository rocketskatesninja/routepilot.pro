<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
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
    public function update(ProfileUpdateRequest $request, PhotoService $photos): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->only(['first_name', 'last_name', 'email']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $photo = $request->file('photo');
        if ($photo instanceof UploadedFile) {
            $old = $user->getAttribute('avatar_path');
            $user->forceFill(['avatar_path' => $photos->replace($photo, is_string($old) ? $old : null, 'avatars')]);
        }

        $user->save();

        return to_route('profile.edit');
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
