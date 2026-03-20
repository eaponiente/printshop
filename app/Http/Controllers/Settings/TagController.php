<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Tag;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:tags,name',
            ],
        ]);

        Tag::create([
            'name' => $validated['name'],
        ]);

        return redirect()->back();
    }

    public function update(Request $request, Tag $tag)
    {
        $this->authorize('update', auth()->user());

        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tags', 'name')->ignore($tag->id),
            ],
        ];

        $validated = $request->validate($rules);

        $tag->update($validated);

        return redirect()->back();
    }

    public function destroy(Tag $tag)
    {
        $this->authorize('delete', auth()->user());

        $tag->delete();
    }
}
