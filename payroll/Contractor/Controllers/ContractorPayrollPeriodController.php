<?php

namespace Payroll\Contractor\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payroll\PayrollPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Payroll\Contractor\Services\ContractorPayrollPeriodService;

class ContractorPayrollPeriodController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = PayrollPeriod::with(['branch:id,name', 'contractorItems.contractor:id,name', 'contractorItems.project:id,name,contract_amount'])
            ->whereHas('contractorItems');

        if ($user->isAdmin()) {
            $query->where('branch_id', $user->branch_id);
        }

        $periods = $query->orderBy('period_start', 'desc')->paginate(20);

        $branches = Branch::orderBy('name')->get(['id', 'name']);

        return Inertia::render('payroll/payroll/contractor-periods', [
            'periods' => $periods,
            'branches' => $branches,
        ]);
    }

    public function generate(Request $request, ContractorPayrollPeriodService $service)
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $branch = Branch::findOrFail($validated['branch_id']);

        $service->generate($branch, $validated['period_start'], $validated['period_end']);

        return back()->with('success', 'Contractor payroll period generated.');
    }

    public function show(PayrollPeriod $period)
    {
        $period->load(['branch:id,name', 'contractorItems.contractor:id,name', 'contractorItems.project:id,name,contract_amount']);

        return Inertia::render('payroll/payroll/contractor-period-show', [
            'period' => $period,
        ]);
    }

    public function approve(PayrollPeriod $period, ContractorPayrollPeriodService $service)
    {
        $service->approve($period, auth()->id());

        return back()->with('success', 'Contractor payroll period approved.');
    }

    public function destroy(PayrollPeriod $period, ContractorPayrollPeriodService $service)
    {
        $service->delete($period);

        return back()->with('success', 'Contractor payroll period deleted.');
    }
}
