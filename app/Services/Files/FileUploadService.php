<?php

namespace App\Services\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class FileUploadService
{
    /**
     * Upload a file and return its path.
     */
    public function upload(UploadedFile $file, string $directory = 'uploads'): string
    {
        return $file->store($directory, 's3');
    }

    /**
     * Get signed URL for a file.
     */
    public function getSignedUrl(string $s3Path): string
    {
        $cacheKey = "s3_signed_url:{$s3Path}";

        return Cache::remember(
            $cacheKey,
            $expiresAt = now()->addHours(3),
            fn () => Storage::disk('s3')->temporaryUrl($s3Path, $expiresAt)
        );
    }

    /**
     * Delete an existing file if it exists.
     */
    public function delete(?string $path): void
    {
        if ($path && Storage::disk('s3')->exists($path)) {
            Storage::disk('s3')->delete($path);
        }
    }
}
