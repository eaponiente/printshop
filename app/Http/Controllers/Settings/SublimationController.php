<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Sublimations\SublimationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreSublimationRequest;
use App\Http\Requests\Settings\UpdateSublimationRequest;
use App\Models\Branch;
use App\Models\Sublimation;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SublimationController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $query = Sublimation::with('tags', 'branch', 'user', 'customer');

        $query->when($request->filled('branch_id') && $request->branch_id !== 'all', function ($q) use ($request) {
            $q->where('branch_id', $request->branch_id);
        });

        $filters = $request->all();

        $query->where(function ($query) use ($filters) {
            $user = auth()->user();
            $filterId = $filters['branch_id'] ?? null;

            if ($user->role !== 'superadmin') {
                // Non-admins are FORCED to their branch, regardless of the filter
                $query->where('branch_id', $user->branch_id);
            } elseif ($filterId && $filterId !== 'all') {
                // Superadmins only get a WHERE clause if they picked a specific branch
                $query->where('branch_id', $filterId);
            }
        });

        $query->when($request->filled('status') && $request->status !== 'all', function ($q) use ($request) {
            $q->where('status', $request->status);
        });

        $query->when(
            ! $request->boolean('include_completed'),
            function ($q) {
                // If we are NOT including completed, we filter them out
                $q->where('status', '!=', 'completed');
            }
        );

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
            'branches' => Branch::accessibleBy(auth()->user())->get(['id', 'name']),
            'users' => User::all(['id', 'first_name', 'last_name']),
            'statuses' => SublimationStatus::map(),
        ]);
    }

    public function store(StoreSublimationRequest $request): RedirectResponse
    {
        try {
            Sublimation::create($request->validated());

            return redirect()->back()->with('success', 'Sublimation created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create sublimation: '.$e->getMessage());

            return redirect()->back()->withErrors(['error' => 'An error occurred while creating the sublimation.']);
        }
    }

    public function update(UpdateSublimationRequest $request, Sublimation $sublimation): RedirectResponse
    {
        $this->authorize('update', auth()->user());

        try {
            $sublimation->update($request->validated());

            return redirect()->back()->with('success', 'Sublimation updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update sublimation: '.$e->getMessage());

            return redirect()->back()->withErrors(['error' => 'An error occurred while updating the sublimation.']);
        }
    }

    public function destroy(Sublimation $sublimation): RedirectResponse
    {
        $this->authorize('delete', auth()->user());

        try {
            $sublimation->delete();

            return redirect()->back()->with('success', 'Sublimation deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete sublimation: '.$e->getMessage());

            return redirect()->back()->withErrors(['error' => 'An error occurred while deleting the sublimation.']);
        }
    }

    public function complete(Sublimation $sublimation): RedirectResponse
    {
        try {
            $sublimation->update(['status' => SublimationStatus::COMPLETED]);

            return back()->with('success', 'Transaction marked as completed.');
        } catch (\Exception $e) {
            Log::error('Failed to mark sublimation as completed: '.$e->getMessage());

            return back()->withErrors(['error' => 'An error occurred while completing the sublimation.']);
        }
    }
}
