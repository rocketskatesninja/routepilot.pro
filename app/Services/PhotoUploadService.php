<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PhotoUploadService
{
    /**
     * Handle photo uploads for a request.
     */
    public function handlePhotoUploads(Request $request, string $directory, array $oldPhotos = []): array
    {
        if (!$request->hasFile('photos')) {
            return $oldPhotos;
        }

        // Delete old photos if updating
        if (!empty($oldPhotos)) {
            $this->deletePhotos($oldPhotos);
        }

        $photoPaths = [];
        foreach ($request->file('photos') as $photo) {
            $path = $photo->store($directory, 'public');
            $photoPaths[] = $path;
        }

        return $photoPaths;
    }

    /**
     * Handle single photo upload.
     */
    public function handleSinglePhotoUpload(Request $request, string $directory, ?string $oldPhoto = null): ?string
    {
        if (!$request->hasFile('profile_photo')) {
            return $oldPhoto;
        }

        // Delete old photo if exists
        if ($oldPhoto) {
            $this->deletePhoto($oldPhoto);
        }

        return $request->file('profile_photo')->store($directory, 'public');
    }

    /**
     * Delete multiple photos from storage.
     */
    private function deletePhotos(array $photos): void
    {
        foreach ($photos as $photo) {
            $this->deletePhoto($photo);
        }
    }

    /**
     * Delete a single photo from storage.
     */
    private function deletePhoto(?string $photo): void
    {
        if ($photo) {
            Storage::disk('public')->delete($photo);
        }
    }
} 