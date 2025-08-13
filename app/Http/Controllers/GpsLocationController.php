<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class GpsLocationController extends Controller
{
    /**
     * Update the current user's GPS location.
     */
    public function updateLocation(Request $request): JsonResponse
    {
        // Ensure user is authenticated and is a technician
        if (!auth()->check() || !auth()->user()->isTechnician()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        try {
            $user = auth()->user();
            $success = $user->updateCurrentLocation(
                $request->latitude,
                $request->longitude
            );

            if ($success) {
                Log::info('Technician GPS location updated', [
                    'user_id' => $user->id,
                    'name' => $user->full_name,
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Location updated successfully',
                    'location' => $user->getCurrentCoordinates(),
                    'updated_at' => $user->location_updated_at,
                ]);
            }

            return response()->json(['error' => 'Failed to update location'], 500);

        } catch (\Exception $e) {
            Log::error('Error updating technician GPS location', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Get the current user's GPS location.
     */
    public function getLocation(): JsonResponse
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = auth()->user();
        
        if (!$user->hasCurrentLocation()) {
            return response()->json(['error' => 'No location data available'], 404);
        }

        return response()->json([
            'success' => true,
            'location' => $user->getCurrentCoordinates(),
            'updated_at' => $user->location_updated_at,
            'age_minutes' => $user->getLocationAge(),
            'is_recent' => $user->hasRecentLocation(),
            'location_sharing_enabled' => $user->location_sharing_enabled,
        ]);
    }

    /**
     * Toggle location sharing for the current user.
     */
    public function toggleLocationSharing(Request $request): JsonResponse
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $user = auth()->user();
        $user->location_sharing_enabled = $request->enabled;
        
        // If disabling location sharing, clear GPS data
        if (!$request->enabled) {
            $user->current_latitude = null;
            $user->current_longitude = null;
            $user->location_updated_at = null;
        }
        
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Location sharing ' . ($request->enabled ? 'enabled' : 'disabled'),
            'location_sharing_enabled' => $user->location_sharing_enabled,
        ]);
    }

    /**
     * Get the current user's location sharing status.
     */
    public function getLocationSharingStatus(): JsonResponse
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = auth()->user();
        
        return response()->json([
            'success' => true,
            'location_sharing_enabled' => $user->location_sharing_enabled,
            'has_location' => $user->hasCurrentLocation(),
            'location' => $user->getCurrentCoordinates(),
            'updated_at' => $user->location_updated_at,
        ]);
    }
}
