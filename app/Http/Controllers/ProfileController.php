<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use App\Models\Activity;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        
        // Get validated data
        $validated = $request->validated();
        
        // Handle service reports logic
        if (!$validated['service_reports_enabled']) {
            $validated['service_reports'] = 'none';
        }
        unset($validated['service_reports_enabled']);
        
        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            // Delete old photo if exists
            if ($user->profile_photo) {
                Storage::disk('public')->delete($user->profile_photo);
            }
            
            $path = $request->file('profile_photo')->store('profile-photos', 'public');
            $validated['profile_photo'] = $path;
        }
        
        // Handle password update - only if provided
        if (!empty($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            // Remove password from validated data if not provided
            unset($validated['password']);
        }
        
        // Remove current_password from validated data as it's not a database field
        unset($validated['current_password']);
        unset($validated['password_confirmation']);
        
        // Update user with validated data
        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('success', 'Profile updated successfully!');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Delete the user's profile photo (AJAX).
     */
    public function deletePhoto(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->profile_photo) {
            return response()->json(['error' => 'No profile photo found'], 404);
        }
        
        // Delete from storage
        Storage::disk('public')->delete($user->profile_photo);
        
        // Clear the profile photo field
        $user->profile_photo = null;
        $user->save();
        
        // Log activity
        Activity::log('delete', "Deleted profile photo", $user, $user);
        
        return response()->json(['success' => true]);
    }
}
