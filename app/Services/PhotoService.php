<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

/**
 * Resize + store uploaded images on the public disk. Normalizes everything to
 * JPEG (or PNG where a transparent background matters, like a logo) and scales
 * down to fit within a max box (never upscales), so a multi-MB phone photo
 * becomes a reasonable web image. Harvested from the legacy app.
 *
 * Returns the stored path (relative to the public disk); render it with
 * Storage::url() / asset('storage/...'). All single-photo entities + visit
 * photos share this service.
 */
class PhotoService
{
    /**
     * Store a resized JPEG copy of the upload (PNG with $transparent, which JPEG
     * would fill in white); returns the stored path.
     */
    public function store(UploadedFile $file, string $directory, int $max = 1200, int $quality = 85, bool $transparent = false): string
    {
        $path = trim($directory, '/').'/'.Str::uuid()->toString().($transparent ? '.png' : '.jpg');

        $image = ImageManager::gd()->read($file->getRealPath());
        $image->scaleDown($max, $max);

        Storage::disk('public')->put($path, (string) ($transparent ? $image->toPng() : $image->toJpeg($quality)));

        return $path;
    }

    /**
     * Store the new image and delete the old one; returns the new path.
     */
    public function replace(UploadedFile $file, ?string $oldPath, string $directory, int $max = 1200, int $quality = 85, bool $transparent = false): string
    {
        $this->delete($oldPath);

        return $this->store($file, $directory, $max, $quality, $transparent);
    }

    /**
     * Attach an uploaded photo to a model's path column (store new / replace old),
     * a no-op when $photo isn't an upload. The privilege-ish path column is set via
     * forceFill, matching the existing call sites.
     */
    public function attach(Model $model, mixed $photo, string $column, string $dir): void
    {
        if (! $photo instanceof UploadedFile) {
            return;
        }

        $old = $model->getAttribute($column);
        $model->forceFill([$column => $this->replace($photo, is_string($old) ? $old : null, $dir)])->save();
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
