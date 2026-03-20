<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Sublimation;
use App\Models\Tag;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SublimationController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        return Inertia::render('sublimations/list', [
            'sublimations' => Sublimation::with('tags', 'branch', 'user')->paginate(30)->withQueryString(),
            'availableTags' => Tag::all(['id', 'name', 'color']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:sublimations,name',
            ],
        ]);

        Sublimation::create([
            'name' => $validated['name'],
        ]);

        return redirect()->back();
    }

    public function update(Request $request, Sublimation $sublimation)
    {
        $this->authorize('update', auth()->user());

        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tags', 'name')->ignore($sublimation->id),
            ],
        ];

        $validated = $request->validate($rules);

        $sublimation->update($validated);

        return redirect()->back();
    }

    public function destroy(Sublimation $sublimation)
    {
        $this->authorize('delete', auth()->user());

        $sublimation->delete();
    }
}
