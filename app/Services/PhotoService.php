<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

/**
 * Resize + store uploaded images on the public disk. Normalizes everything to
 * JPEG and scales down to fit within a max box (never upscales), so a multi-MB
 * phone photo becomes a reasonable web image. Harvested from the legacy app.
 *
 * Returns the stored path (relative to the public disk); render it with
 * Storage::url() / asset('storage/...'). All single-photo entities + visit
 * photos share this service.
 */
class PhotoService
{
    /**
     * Store a resized JPEG copy of the upload; returns the stored path.
     */
    public function store(UploadedFile $file, string $directory, int $max = 1200, int $quality = 85): string
    {
        $path = trim($directory, '/').'/'.Str::uuid()->toString().'.jpg';

        $image = ImageManager::gd()->read($file->getRealPath());
        $image->scaleDown($max, $max);

        Storage::disk('public')->put($path, (string) $image->toJpeg($quality));

        return $path;
    }

    /**
     * Store the new image and delete the old one; returns the new path.
     */
    public function replace(UploadedFile $file, ?string $oldPath, string $directory, int $max = 1200, int $quality = 85): string
    {
        $this->delete($oldPath);

        return $this->store($file, $directory, $max, $quality);
    }

    /** Delete a stored image (no-op for null/empty). */
    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
