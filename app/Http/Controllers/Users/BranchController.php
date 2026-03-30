<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        return Inertia::render('branches/list', [
            'branches' => Branch::where('branch_id', auth()->user()->branch_id)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        Branch::create([
            'name' => $validated['name'],
        ]);

        return redirect()->back();
    }

    public function update(Request $request, Branch $branch)
    {
        $this->authorize('update', auth()->user());

        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
        ];

        $validated = $request->validate($rules);

        $branch->update($validated);

        return redirect()->back();
    }

    public function destroy(Branch $branch)
    {
        $this->authorize('delete', auth()->user());

        if ($branch->users()->count() > 0) {
            abort(403, 'You cannot delete this branch yet as it still has some users.');
        }

        $branch->delete();
    }
}
