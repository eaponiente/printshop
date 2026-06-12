<?php

namespace Payroll\Contractor\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payroll\ContractorCashAdvance;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ContractorCashAdvanceController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = ContractorCashAdvance::with(['contractor:id,name']);

        if ($user->isAdmin()) {
            $query->whereHas('contractor', fn ($q) => $q->where('branch_id', $user->branch_id));
        } elseif ($user->isStaff()) {
            abort(403);
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(20);

        return Inertia::render('payroll/requests/contractor-cash-advances', [
            'requests' => $requests,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'contractor_id' => ['required', 'exists:contractors,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $existing = ContractorCashAdvance::where('contractor_id', $validated['contractor_id'])
            ->whereIn('status', ['pending', 'approved', 'unpaid'])
            ->where('remaining_balance', '>', 0)
            ->exists();

        if ($existing) {
            return back()->withErrors(['error' => 'An active cash advance already exists for this contractor.']);
        }

        ContractorCashAdvance::create([
            'contractor_id' => $validated['contractor_id'],
            'amount' => $validated['amount'],
            'remaining_balance' => $validated['amount'],
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        return back()->with('success', 'Cash advance request submitted.');
    }

    public function approve(ContractorCashAdvance $cashAdvance)
    {
        if ($cashAdvance->status !== 'pending') {
            return back()->withErrors(['error' => 'Only pending requests can be approved.']);
        }

        $cashAdvance->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Cash advance approved.');
    }

    public function deny(Request $request, ContractorCashAdvance $cashAdvance)
    {
        if ($cashAdvance->status !== 'pending') {
            return back()->withErrors(['error' => 'Only pending requests can be denied.']);
        }

        $cashAdvance->update([
            'status' => 'denied',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Cash advance denied.');
    }
}
