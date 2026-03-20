<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Sublimation;
use App\Models\Tag;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SublimationTagController extends Controller
{
    use AuthorizesRequests;

    public function addTag(Request $request, Sublimation $sublimation)
    {
        $request->validate([
            'tag_id' => 'required|exists:tags,id',
        ]);

        // syncWithoutDetaching prevents duplicate entries in the pivot table
        $sublimation->tags()->syncWithoutDetaching([$request->tag_id]);

        return back();
    }

    public function removeTag(Sublimation $sublimation, Tag $tag)
    {
        $sublimation->tags()->detach($tag->id);

        return back();
    }
}
