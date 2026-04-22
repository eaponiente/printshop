<?php

namespace App\Http\Controllers\Sublimations;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Sublimation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SublimationImageController extends Controller
{
    /**
     * The TTL (in minutes) for cached presigned URLs.
     * Must be less than the URL expiry window (3 hours = 180 min).
     */
    private const URL_CACHE_TTL_MINUTES = 175;

    public function index(Sublimation $sublimation)
    {
        // Generate all temporary URLs in one collection pass.
        // Each URL is cached individually — subsequent gallery opens within
        // the TTL window skip S3 signing entirely (cache hit).
        $images = $sublimation->images->map(function ($image) {
            $signedUrl = $this->resolveTemporaryUrl($image->id, $image->url);

            return [
                'id'       => $image->id,
                'url'      => $signedUrl,
                'raw_path' => $image->url,
                'name'     => basename($image->url),
            ];
        });

        return response()->json($images);
    }

    /**
     * Return a cached presigned URL for the given S3 path.
     * Signs and caches on cache miss; returns the cached value on hit.
     */
    private function resolveTemporaryUrl(int $imageId, string $s3Path): string
    {
        $cacheKey = "s3_signed_url:{$imageId}";
        $expiresAt = now()->addHours(3);

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(self::URL_CACHE_TTL_MINUTES),
            fn() => Storage::disk('s3')->temporaryUrl($s3Path, $expiresAt)
        );
    }

    public function store(Request $request, Sublimation $sublimation)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        try {

            if ($sublimation->images()->count() === 10) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum number of images reached.',
                ], 400);
            }

            $file = $request->file('image');

            // Log the attempt so you see it in Railway
            Log::info("Starting upload for: " . $file->getClientOriginalName());

            try {
                $path = $file->store('sublimation_images', [
                    'disk' => 's3',
                    'visibility' => 'public'
                ]);
            } catch (\Throwable $sdkException) {
                Log::error("S3 SDK EXCEPTION during store()", [
                    'aws_error' => $sdkException->getMessage(),
                    'exception_class' => get_class($sdkException),
                    'file' => $sdkException->getFile(),
                    'line' => $sdkException->getLine(),
                    'trace' => substr($sdkException->getTraceAsString(), 0, 1000),
                ]);
                throw $sdkException;
            }

            if (!$path) {
                throw new \Exception("File could not be saved to S3 - path returned null.");
            }

            $image = $sublimation->images()->create([
                'url' => $path,
            ]);

            // Prime the cache immediately so the first gallery open after
            // upload is also a cache hit rather than a second signing operation.
            $signedUrl = $this->resolveTemporaryUrl($image->id, $path);

            return response()->json([
                'success' => true,
                'id'      => $image->id,
                'url'     => $signedUrl,
                'name'    => basename($path),
            ], 201);
        } catch (\Exception $e) {
            Log::error('S3 upload failed', [
                'sublimation_id' => $sublimation->id,
                'filename'       => $request->file('image')?->getClientOriginalName(),
                'error'          => $e->getMessage(),
                'trace'          => substr($e->getTraceAsString(), 0, 500),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Upload failed.',
                'debug'   => config('app.debug') ? $e->getMessage() : 'Check server logs',
            ], 500);
        }
    }

    public function destroy(Sublimation $sublimation, Image $image)
    {
        // Ensure this image belongs to this sublimation
        if ($image->imageable_id !== $sublimation->id || $image->imageable_type !== Sublimation::class) {
            abort(403, 'Unauthorized access to this image.');
        }

        // Invalidate the cached presigned URL before deleting the object
        Cache::forget("s3_signed_url:{$image->id}");

        // Remove from disk then purge the DB record
        Storage::disk('s3')->delete($image->url);
        $image->delete();

        return response()->json(['success' => true]);
    }
}
