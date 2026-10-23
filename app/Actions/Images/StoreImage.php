<?php

namespace App\Actions\Images;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores an uploaded image on the public disk.
 *
 * The file is decoded and re-encoded as WebP instead of being stored as
 * uploaded: that drops metadata (such as GPS position in photos) and anything
 * hidden in the file, and caps the size. Returns the path on the public disk.
 */
class StoreImage
{
    public function handle(UploadedFile $file, string $folder, int $maxDimension = 1024, string $field = 'image'): string
    {
        return $this->fromFile((string) $file->getRealPath(), $folder, $maxDimension, $field);
    }

    /**
     * Same as handle(), for an image file on disk (used when copying the old app's images).
     */
    public function fromFile(string $path, string $folder, int $maxDimension = 1024, string $field = 'image'): string
    {
        return $this->fromBytes((string) file_get_contents($path), $folder, $maxDimension, $field);
    }

    /**
     * Same as handle(), for the bytes of an image (attachments and pictures fetched from a website).
     */
    public function fromBytes(string $bytes, string $folder, int $maxDimension = 1024, string $field = 'image'): string
    {
        $image = $bytes === '' ? false : @imagecreatefromstring($bytes);

        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages([$field => 'This file could not be read as an image.']);
        }

        $image = $this->fitWithin($image, $maxDimension);
        // Keep the transparent parts of logos transparent.
        imagesavealpha($image, true);

        ob_start();
        imagewebp($image, null, 85);
        $webp = (string) ob_get_clean();

        $path = trim($folder, '/').'/'.Str::uuid().'.webp';
        Storage::disk('public')->put($path, $webp);

        return $path;
    }

    /**
     * Delete an image this app stored earlier. Values from the old app (bare
     * file names) are left alone: those files live on the old server.
     */
    public function delete(?string $path): void
    {
        // Patterns ("pattern/…") ship with the website and are never deleted.
        if ($path !== null && str_contains($path, '/') && ! str_contains($path, '..') && ! str_starts_with($path, 'pattern/')) {
            Storage::disk('public')->delete($path);
        }
    }

    private function fitWithin(GdImage $image, int $maxDimension): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $maxDimension / max($width, $height));

        if ($scale >= 1) {
            return $image;
        }

        $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));

        return $resized instanceof GdImage ? $resized : $image;
    }
}
