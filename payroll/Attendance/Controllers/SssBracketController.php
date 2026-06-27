<?php

namespace Payroll\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payroll\SssContributionBracket;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SssBracketController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $brackets = SssContributionBracket::orderBy('salary_min')->get();

        return Inertia::render('payroll/settings/sss-brackets', [
            'brackets' => $brackets,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'salary_min' => ['required', 'numeric', 'min:0'],
            'salary_max' => ['nullable', 'numeric', 'gt:salary_min'],
            'employee_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'employer_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'effective_from' => ['required', 'date'],
        ]);

        SssContributionBracket::create($validated);

        return back()->with('success', 'Bracket added.');
    }

    public function update(Request $request, SssContributionBracket $bracket)
    {
        $validated = $request->validate([
            'salary_min' => ['required', 'numeric', 'min:0'],
            'salary_max' => ['nullable', 'numeric', 'gt:salary_min'],
            'employee_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'employer_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'effective_from' => ['required', 'date'],
        ]);

        $bracket->update($validated);

        return back()->with('success', 'Bracket updated.');
    }

    public function destroy(SssContributionBracket $bracket)
    {
        $bracket->delete();

        return back()->with('success', 'Bracket removed.');
    }
}
