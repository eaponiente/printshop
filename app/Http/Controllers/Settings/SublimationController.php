<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Sublimation;
use App\Models\Tag;
use App\Models\User;
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
        $filters = $request->only(['branch_id', 'tags', 'user']);

        $query = Sublimation::with('tags', 'branch', 'user', 'customer');

        $query->when($request->filled('branch_id') && $request->branch_id !== 'all', function ($q) use ($request) {
            $q->where('branch_id', $request->branch_id);
        });

        $query->when($request->tags, function ($q) use ($request) {
            $tagIds = explode(',', $request->tags);

            // Use whereHas to query the many-to-many relationship
            $q->whereHas('tags', function ($query) use ($tagIds) {
                $query->whereIn('tags.id', $tagIds);
            });
        });

        // 2. Sorting Logic
        $sortField = $request->query('sort_field', 'created_at'); // Default sort
        $sortDirection = $request->query('sort_direction', 'desc');

        // Whitelist allowed sortable columns
        $allowedSorts = ['due_at'];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        return Inertia::render('sublimations/list', [
            'sublimations' => $query->paginate(30)->withQueryString(),
            'availableTags' => Tag::all(['id', 'name', 'color']),
            'filters' => $request->all(),
            'branches' => Branch::all(['id', 'name']),
            'users' => User::all(['id', 'first_name', 'last_name']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => ['required', 'string'],
            'branch_id' => ['required', Rule::exists('branches', 'id')],
            'customer_id' => ['required', Rule::exists('customers', 'id')],
            'user_id' => ['required', Rule::exists('users', 'id')],
            'status' => ['required', 'in:pending,active,finished,released'],
            'due_at' => ['required', 'date'],
        ]);

        Sublimation::create($validated);

        return redirect()->back();
    }

    public function update(Request $request, Sublimation $sublimation)
    {
        $this->authorize('update', auth()->user());

        $validated = $request->validate([
            'description' => ['required', 'string'],
            'branch_id' => ['required', Rule::exists('branches', 'id')],
            'customer_id' => ['required', Rule::exists('customers', 'id')],
            'user_id' => ['required', Rule::exists('users', 'id')],
            'status' => ['required', 'in:pending,active,finished,released'],
            'due_at' => ['required', 'date'],
        ]);

        $sublimation->update($validated);

        return redirect()->back();
    }

    public function destroy(Sublimation $sublimation)
    {
        $this->authorize('delete', auth()->user());

        $sublimation->delete();
    }
}
