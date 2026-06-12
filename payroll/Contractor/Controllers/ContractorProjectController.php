<?php

namespace Payroll\Contractor\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payroll\Contractor;
use App\Models\Payroll\ContractorProject;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContractorProjectController extends Controller
{
    public function index(Contractor $contractor)
    {
        $projects = $contractor->projects()->orderBy('created_at', 'desc')->get();

        return Inertia::render('payroll/contractors/projects', [
            'contractor' => $contractor,
            'projects' => $projects,
        ]);
    }

    public function store(Request $request, Contractor $contractor)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contract_amount' => ['required', 'numeric', 'min:1'],
            'total_installments' => ['required', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'notes' => ['nullable', 'string'],
        ]);

        $project = ContractorProject::create([
            'contractor_id' => $contractor->id,
            'name' => $validated['name'],
            'contract_amount' => $validated['contract_amount'],
            'total_installments' => $validated['total_installments'],
            'remaining_installments' => 0,
            'installment_amount' => round($validated['contract_amount'] / $validated['total_installments'], 2),
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => 'draft',
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Project added.');
    }

    public function update(Request $request, ContractorProject $project)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'notes' => ['nullable', 'string'],
        ]);

        $project->update($validated);

        return back()->with('success', 'Project updated.');
    }

    public function activate(ContractorProject $project)
    {
        if ($project->status !== 'draft') {
            return back()->withErrors(['error' => 'Only draft projects can be activated.']);
        }

        $project->activate();

        return back()->with('success', 'Project activated.');
    }

    public function destroy(ContractorProject $project)
    {
        if ($project->payrollItems()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete project with existing payroll items.']);
        }

        $project->delete();

        return back()->with('success', 'Project deleted.');
    }
}
