<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Sublimation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SublimationImageController extends Controller
{
    public function index(Sublimation $sublimation)
    {
        $images = $sublimation->images->map(function ($image) {
            return [
                'id' => $image->id,
                'url' => Storage::disk('s3')->temporaryUrl($image->url, now()->addHours(3)),
                'raw_path' => $image->url,
                'name' => basename($image->url),
            ];
        });

        return response()->json($images);
    }

    public function store(Request $request, Sublimation $sublimation)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        try {
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

            return response()->json([
                'success' => true,
                'id' => $image->id,
                'url' => Storage::disk('s3')->url($path),
                'name' => basename($path),
            ], 201);
        } catch (\Exception $e) {
            // --- THIS IS THE CRITICAL LOGGING PART ---
            Log::error("S3 UPLOAD CRITICAL FAILURE", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                // This captures the exact error from the AWS SDK (e.g., SignatureMismatch or AccessDenied)
                'trace' => substr($e->getTraceAsString(), 0, 500),
                'input_filename' => $request->file('image')->getClientOriginalName(),
                'config_check' => [
                    'bucket' => config('filesystems.disks.s3.bucket'),
                    'endpoint' => config('filesystems.disks.s3.endpoint'),
                    'region' => config('filesystems.disks.s3.region'),
                    'url' => config('filesystems.disks.s3.url'),
                ]
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Upload failed.',
                'debug' => config('app.debug') ? $e->getMessage() : 'Check server logs'
            ], 500);
        }
    }

    public function destroy(Sublimation $sublimation, Image $image)
    {
        // Ensure this image belongs to this sublimation
        if ($image->imageable_id !== $sublimation->id || $image->imageable_type !== Sublimation::class) {
            abort(403, 'Unauthorized access to this image.');
        }

        // Remove from disk
        Storage::disk('s3')->delete($image->url);

        // Delete database record
        $image->delete();

        return response()->json(['success' => true]);
    }
}
