<?php

namespace App\Http\Controllers\Sublimations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AddSublimationTagRequest;
use App\Models\Sublimation;
use App\Models\Tag;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SublimationTagController extends Controller
{
    use AuthorizesRequests;

    public function addTag(AddSublimationTagRequest $request, Sublimation $sublimation): RedirectResponse
    {
        if ($sublimation->tagsLocked()) {
            return back()->withErrors(['error' => 'Categories are locked for this sublimation status.']);
        }

        try {
            $sublimation->tags()->syncWithoutDetaching([$request->tag_id => ['quantity' => 1]]);

            return back()->with('success', 'Tag added successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to add tag: '.$e->getMessage());

            return back()->withErrors(['error' => 'An error occurred while adding the tag.']);
        }
    }

    public function removeTag(Sublimation $sublimation, Tag $tag): RedirectResponse
    {
        if ($sublimation->tagsLocked()) {
            return back()->withErrors(['error' => 'Categories are locked for this sublimation status.']);
        }

        try {
            $sublimation->tags()->detach($tag->id);

            return back()->with('success', 'Tag removed successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to remove tag: '.$e->getMessage());

            return back()->withErrors(['error' => 'An error occurred while removing the tag.']);
        }
    }

    public function updateQuantity(Request $request, Sublimation $sublimation, Tag $tag): RedirectResponse
    {
        if ($sublimation->tagsLocked()) {
            return back()->withErrors(['error' => 'Categories are locked for this sublimation status.']);
        }

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);

        try {
            $sublimation->tags()->updateExistingPivot($tag->id, ['quantity' => $data['quantity']]);

            return back()->with('success', 'Quantity updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update tag quantity: '.$e->getMessage());

            return back()->withErrors(['error' => 'An error occurred while updating the quantity.']);
        }
    }
}
