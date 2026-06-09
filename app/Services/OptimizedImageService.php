<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class OptimizedImageService
{
    /**
     * Resize to fit within bounds, encode JPEG, and shrink quality until under budget.
     */
    public function encodeJpegWithinBudget(
        UploadedFile $file,
        int $maxWidth,
        int $maxHeight,
        int $maxBytes,
        int $minQuality = 50,
    ): string {
        $source = $this->loadImage($file);
        $width = imagesx($source);
        $height = imagesy($source);

        $scale = min($maxWidth / max(1, $width), $maxHeight / max(1, $height), 1.0);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($canvas === false) {
            imagedestroy($source);

            throw new RuntimeException('Unable to allocate image canvas.');
        }

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($source);

        $quality = 88;
        $binary = $this->jpegBinary($canvas, $quality);

        while (strlen($binary) > $maxBytes && $quality > $minQuality) {
            $quality -= 6;
            $binary = $this->jpegBinary($canvas, $quality);
        }

        if (strlen($binary) > $maxBytes) {
            $reduced = $this->downscaleCanvas($canvas, 0.88);
            imagedestroy($canvas);
            $canvas = $reduced;
            $quality = 82;
            $binary = $this->jpegBinary($canvas, $quality);

            while (strlen($binary) > $maxBytes && $quality > $minQuality) {
                $quality -= 6;
                $binary = $this->jpegBinary($canvas, $quality);
            }
        }

        imagedestroy($canvas);

        if (strlen($binary) > $maxBytes) {
            throw new RuntimeException('Image could not be compressed below the size limit.');
        }

        return $binary;
    }

    public function encodeSquarePortraitJpeg(UploadedFile $file, int $size, int $maxBytes): string
    {
        return $this->encodeJpegWithinBudget($file, $size, $size, $maxBytes);
    }

    private function loadImage(UploadedFile $file): GdImage
    {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('GD extension is required for image processing.');
        }

        $path = $file->getRealPath();
        if ($path === false) {
            throw new RuntimeException('Uploaded image is not readable.');
        }

        $mime = strtolower((string) $file->getMimeType());
        $image = match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        if ($image === false) {
            throw new RuntimeException('Unsupported or corrupt image upload.');
        }

        return $image;
    }

    private function jpegBinary(GdImage $canvas, int $quality): string
    {
        ob_start();
        imagejpeg($canvas, null, max(40, min(95, $quality)));

        return (string) ob_get_clean();
    }

    private function downscaleCanvas(GdImage $canvas, float $factor): GdImage
    {
        $width = imagesx($canvas);
        $height = imagesy($canvas);
        $targetWidth = max(1, (int) round($width * $factor));
        $targetHeight = max(1, (int) round($height * $factor));

        $reduced = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($reduced === false) {
            throw new RuntimeException('Unable to downscale image.');
        }

        imagecopyresampled($reduced, $canvas, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $reduced;
    }
}
