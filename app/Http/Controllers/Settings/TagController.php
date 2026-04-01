<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Tag;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Requests\Settings\StoreTagRequest;
use App\Http\Requests\Settings\UpdateTagRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        return Inertia::render('tags/list', [
            'tags' => Tag::get(),
        ]);
    }

    public function store(StoreTagRequest $request): RedirectResponse
    {
        try {
            Tag::create([
                'name' => $request->name,
            ]);

            return redirect()->back()->with('success', 'Tag created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create tag: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'An error occurred while creating the tag.']);
        }
    }

    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        $this->authorize('update', auth()->user());

        try {
            $tag->update($request->validated());

            return redirect()->back()->with('success', 'Tag updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update tag: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'An error occurred while updating the tag.']);
        }
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $this->authorize('delete', auth()->user());

        try {
            $tag->delete();
            return redirect()->back()->with('success', 'Tag deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete tag: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'An error occurred while deleting the tag.']);
        }
    }
}
