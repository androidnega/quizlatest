<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sensitive files prefer the private ("local") disk; legacy rows may still point at the public disk.
 */
class SensitiveStorageService
{
    public function existsOnPrivate(string $relativePath): bool
    {
        return $relativePath !== '' && Storage::disk('local')->exists($relativePath);
    }

    public function existsOnPublic(string $relativePath): bool
    {
        return $relativePath !== '' && Storage::disk('public')->exists($relativePath);
    }

    public function existsAnywhere(string $relativePath): bool
    {
        return $this->existsOnPrivate($relativePath) || $this->existsOnPublic($relativePath);
    }

    public function resolveReadDisk(string $relativePath): ?string
    {
        if ($relativePath === '') {
            return null;
        }
        if ($this->existsOnPrivate($relativePath)) {
            return 'local';
        }
        if ($this->existsOnPublic($relativePath)) {
            return 'public';
        }

        return null;
    }

    public function diskForRead(string $relativePath): Filesystem
    {
        $disk = $this->resolveReadDisk($relativePath);
        abort_if($disk === null, 404);

        return Storage::disk($disk);
    }

    public function inlineImageResponse(string $relativePath): Response
    {
        $disk = $this->diskForRead($relativePath);
        $mime = $disk->mimeType($relativePath) ?: 'image/jpeg';

        return response($disk->get($relativePath), 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function downloadResponse(string $relativePath, ?string $downloadName = null): StreamedResponse
    {
        $diskName = $this->resolveReadDisk($relativePath);
        abort_if($diskName === null, 404);

        return Storage::disk($diskName)->download(
            $relativePath,
            $downloadName ?? basename($relativePath),
        );
    }

    public function deleteFromAnywhere(string $relativePath): void
    {
        if ($relativePath === '') {
            return;
        }
        if (Storage::disk('local')->exists($relativePath)) {
            Storage::disk('local')->delete($relativePath);
        }
        if (Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }
    }
}
