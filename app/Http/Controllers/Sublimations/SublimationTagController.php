<?php

namespace App\Http\Controllers\Sublimations;

use App\Http\Controllers\Controller;
use App\Models\Sublimation;
use App\Models\Tag;
use App\Http\Requests\Settings\AddSublimationTagRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class SublimationTagController extends Controller
{
    use AuthorizesRequests;

    public function addTag(AddSublimationTagRequest $request, Sublimation $sublimation): RedirectResponse
    {
        try {
            $sublimation->tags()->syncWithoutDetaching([$request->tag_id]);

            return back()->with('success', 'Tag added successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to add tag: ' . $e->getMessage());
            return back()->withErrors(['error' => 'An error occurred while adding the tag.']);
        }
    }

    public function removeTag(Sublimation $sublimation, Tag $tag): RedirectResponse
    {
        $this->authorize('update', auth()->user());

        try {
            $sublimation->tags()->detach($tag->id);
            return back()->with('success', 'Tag removed successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to remove tag: ' . $e->getMessage());
            return back()->withErrors(['error' => 'An error occurred while removing the tag.']);
        }
    }
}
