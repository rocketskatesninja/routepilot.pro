<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class FileUploadService
{
    /**
     * Maximum file size in bytes
     */
    const MAX_FILE_SIZE = null; // Will be loaded from config

    /**
     * Allowed image mime types
     */
    const ALLOWED_IMAGE_TYPES = null; // Will be loaded from config

    /**
     * Upload a file with proper validation and error handling
     *
     * @param UploadedFile $file
     * @param string $directory
     * @param string|null $oldFile
     * @return array
     */
    public function uploadFile(UploadedFile $file, string $directory, ?string $oldFile = null): array
    {
        try {
            // Get configuration values
            $maxFileSize = config('file-uploads.max_file_size', 25 * 1024 * 1024);
            $allowedTypes = config('file-uploads.allowed_image_types', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']);
            
            // Validate file size
            if ($file->getSize() > $maxFileSize) {
                $maxSizeMB = round($maxFileSize / (1024 * 1024), 1);
                return [
                    'success' => false,
                    'error' => "File size exceeds the maximum allowed size of {$maxSizeMB}MB."
                ];
            }

            // Validate file type
            if (!in_array($file->getMimeType(), $allowedTypes)) {
                $allowedExtensions = implode(', ', array_map(function($type) {
                    return strtoupper(substr($type, 6)); // Remove 'image/' prefix
                }, $allowedTypes));
                return [
                    'success' => false,
                    'error' => "Invalid file type. Only {$allowedExtensions} images are allowed."
                ];
            }

            // Generate unique filename
            $filename = $this->generateUniqueFilename($file);
            $path = $directory . '/' . $filename;

            // Store the file
            $stored = Storage::disk('public')->putFileAs($directory, $file, $filename);
            
            if (!$stored) {
                return [
                    'success' => false,
                    'error' => 'Failed to store the uploaded file.'
                ];
            }

            // Delete old file if it exists
            if ($oldFile && Storage::disk('public')->exists($oldFile)) {
                Storage::disk('public')->delete($oldFile);
                Log::info("Deleted old file: {$oldFile}");
            }

            Log::info("File uploaded successfully", [
                'original_name' => $file->getClientOriginalName(),
                'stored_path' => $path,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType()
            ]);

            return [
                'success' => true,
                'filename' => $filename,
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType()
            ];

        } catch (Exception $e) {
            Log::error("File upload failed", [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
                'size' => $file->getSize()
            ]);

            return [
                'success' => false,
                'error' => 'File upload failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete a file from storage
     *
     * @param string $filePath
     * @return array
     */
    public function deleteFile(string $filePath): array
    {
        try {
            if (!Storage::disk('public')->exists($filePath)) {
                Log::warning("File not found for deletion: {$filePath}");
                return [
                    'success' => false,
                    'error' => 'File not found.'
                ];
            }

            $deleted = Storage::disk('public')->delete($filePath);
            
            if ($deleted) {
                Log::info("File deleted successfully: {$filePath}");
                return [
                    'success' => true,
                    'message' => 'File deleted successfully.'
                ];
            } else {
                Log::warning("Failed to delete file: {$filePath}");
                return [
                    'success' => false,
                    'error' => 'Failed to delete file.'
                ];
            }

        } catch (Exception $e) {
            Log::error("File deletion failed", [
                'error' => $e->getMessage(),
                'file_path' => $filePath
            ]);

            return [
                'success' => false,
                'error' => 'File deletion failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate a unique filename for the uploaded file
     *
     * @param UploadedFile $file
     * @return string
     */
    private function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $baseName = Str::random(40);
        
        return $baseName . '.' . $extension;
    }

    /**
     * Get the maximum file size in human-readable format
     *
     * @return string
     */
    public static function getMaxFileSizeHuman(): string
    {
        $size = config('file-uploads.max_file_size', 25 * 1024 * 1024);
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, 2) . ' . ' . $units[$i];
    }

    /**
     * Get the maximum file size in bytes
     *
     * @return int
     */
    public static function getMaxFileSizeBytes(): int
    {
        return config('file-uploads.max_file_size', 25 * 1024 * 1024);
    }

    /**
     * Get allowed file types
     *
     * @return array
     */
    public static function getFileTypeExtensions(): array
    {
        $allowedTypes = config('file-uploads.allowed_image_types', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']);
        return array_map(function($type) {
            return strtoupper(substr($type, 6)); // Remove 'image/' prefix
        }, $allowedTypes);
    }
}
