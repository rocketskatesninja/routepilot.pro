<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhotoUploadService
{
    /**
     * Upload a single photo
     */
    public static function uploadPhoto(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'public');
    }

    /**
     * Upload multiple photos
     */
    public static function uploadPhotos(array $files, string $directory): array
    {
        $paths = [];
        foreach ($files as $file) {
            $paths[] = $file->store($directory, 'public');
        }
        return $paths;
    }

    /**
     * Delete a photo from storage
     */
    public static function deletePhoto(?string $path): bool
    {
        if ($path) {
            return Storage::disk('public')->delete($path);
        }
        return false;
    }

    /**
     * Delete multiple photos from storage
     */
    public static function deletePhotos(?array $paths): bool
    {
        if ($paths) {
            foreach ($paths as $path) {
                Storage::disk('public')->delete($path);
            }
            return true;
        }
        return false;
    }

    /**
     * Get the storage URL for a photo
     */
    public static function getPhotoUrl(?string $path): ?string
    {
        return $path ? Storage::url($path) : null;
    }

    /**
     * Get storage URLs for multiple photos
     */
    public static function getPhotoUrls(?array $paths): array
    {
        if (!$paths) {
            return [];
        }
        
        return array_map(function ($path) {
            return Storage::url($path);
        }, $paths);
    }

    /**
     * Generate a unique filename
     */
    public static function generateFilename(UploadedFile $file): string
    {
        return Str::uuid() . '.' . $file->getClientOriginalExtension();
    }

    /**
     * Validate photo file
     */
    public static function validatePhoto(UploadedFile $file): bool
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        return in_array($file->getMimeType(), $allowedMimes) && $file->getSize() <= $maxSize;
    }
} 