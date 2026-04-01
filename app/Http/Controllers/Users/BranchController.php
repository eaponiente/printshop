<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreBranchRequest;
use App\Http\Requests\Users\UpdateBranchRequest;
use App\Models\Branch;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Branch::class);

        return Inertia::render('branches/list', [
            'branches' => Branch::query()->get(),
        ]);
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        try {
            Branch::create([
                'name' => $request->name,
            ]);

            return redirect()->back()->with('success', 'Branch created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create branch: '.$e->getMessage());

            return redirect()->back()->withErrors(['error' => 'An error occurred while creating the branch.']);
        }
    }

    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->authorize('update', auth()->user());

        try {
            $branch->update($request->validated());

            return redirect()->back()->with('success', 'Branch updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update branch: '.$e->getMessage());

            return redirect()->back()->withErrors(['error' => 'An error occurred while updating the branch.']);
        }
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorize('delete', auth()->user());

        if ($branch->users()->count() > 0) {
            return redirect()->back()->withErrors(['error' => 'You cannot delete this branch yet as it still has some users.']);
        }

        try {
            $branch->delete();

            return redirect()->back()->with('success', 'Branch deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete branch: '.$e->getMessage());

            return redirect()->back()->withErrors(['error' => 'An error occurred while deleting the branch.']);
        }
    }
}
