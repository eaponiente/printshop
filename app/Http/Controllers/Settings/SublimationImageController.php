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
                'url' => Storage::disk('public')->url($image->url),
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

            // 1. Attempt the upload to S3
            // We use 'public' visibility so the URL is viewable by customers
            $path = $file->store('sublimation_images', [
                'disk' => 's3',
                'visibility' => 'public'
            ]);

            if (!$path) {
                throw new Exception("File could not be saved to S3.");
            }

            // 2. Database transaction/operation
            $image = $sublimation->images()->create([
                'url' => $path,
            ]);

            return response()->json([
                'success' => true,
                'id' => $image->id,
                'url' => Storage::disk('s3')->url($path),
                'name' => basename($path),
            ], 201);
        } catch (Exception $e) {
            // 3. Log the error for you to see in Railway Logs
            Log::error("S3 Upload Error: " . $e->getMessage(), [
                'user_id' => auth()->id(),
                'file' => $request->file('image')->getClientOriginalName()
            ]);

            // 4. Return a clean message to the frontend
            return response()->json([
                'success' => false,
                'message' => 'Upload failed. Please try again later.',
                // Only include 'debug' if you are in local development
                'debug' => config('app.debug') ? $e->getMessage() : null
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
        Storage::disk('public')->delete($image->url);

        // Delete database record
        $image->delete();

        return response()->json(['success' => true]);
    }
}
