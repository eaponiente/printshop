<?php

namespace Payroll\Contractor\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payroll\Contractor;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContractorController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Contractor::with(['branch:id,name', 'projects'])->withCount(['projects as active_projects_count' => fn ($q) => $q->where('status', 'active')]);

        if ($user->isAdmin()) {
            $query->where('branch_id', $user->branch_id);
        } elseif ($user->isStaff()) {
            abort(403);
        }

        $contractors = $query->orderBy('name')->paginate(20);

        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : ($user->isAdmin()
                ? Branch::where('id', $user->branch_id)->get(['id', 'name'])
                : []);

        return Inertia::render('payroll/contractors/index', [
            'contractors' => $contractors,
            'branches' => $branches,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['required', 'exists:branches,id'],
            'notes' => ['nullable', 'string'],
        ]);

        Contractor::create(array_merge($validated, ['status' => 'active']));

        return back()->with('success', 'Contractor added.');
    }

    public function update(Request $request, Contractor $contractor)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $contractor->update($validated);

        return back()->with('success', 'Contractor updated.');
    }

    public function destroy(Contractor $contractor)
    {
        $contractor->delete();

        return back()->with('success', 'Contractor deleted.');
    }
}
