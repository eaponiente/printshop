<?php

namespace Payroll\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payroll\CashAdvance;
use App\Models\Payroll\Employee;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CashAdvanceController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $query = CashAdvance::with(['employee:id,first_name,last_name,branch_id', 'employee.branch:id,name']);
        $user = auth()->user();

        if ($user->isStaff() && $user->employee_id) {
            $query->where('employee_id', $user->employee_id);
        } elseif ($user->isAdmin()) {
            $query->whereHas('employee', fn ($q) => $q->where('branch_id', $user->branch_id));
            $query->whereDoesntHave('employee.user', fn ($q) => $q->where('role', 'superadmin'));
            if ($user->employee_id) {
                $query->where('employee_id', '!=', $user->employee_id);
            }
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(20);

        return Inertia::render('payroll/requests/cash-advances', [
            'requests' => $requests,
        ]);
    }

    public function store(Request $request)
    {
        $employee = Employee::findOrFail($request->user()->employee_id);
        Gate::authorize('cash-advances.request', [$employee->branch_id]);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $activeCA = CashAdvance::where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved', 'unpaid'])
            ->where('remaining_balance', '>', 0)
            ->exists();

        if ($activeCA) {
            return back()->withErrors(['error' => 'You have an unpaid cash advance. Settle it first.']);
        }

        $projectedNet = $employee->current_daily_rate * 6;
        if ($validated['amount'] > $projectedNet) {
            return back()->withErrors(['error' => 'Amount exceeds projected net pay for this period.']);
        }

        CashAdvance::create([
            'employee_id' => $employee->id,
            'amount' => $validated['amount'],
            'remaining_balance' => $validated['amount'],
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        return back()->with('success', 'Cash advance request submitted.');
    }

    public function approve(CashAdvance $cashAdvance)
    {
        Gate::authorize('cash-advances.approve', [$cashAdvance->employee->branch_id, $cashAdvance->employee->user?->id]);

        $cashAdvance->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Cash advance approved.');
    }

    public function deny(CashAdvance $cashAdvance)
    {
        Gate::authorize('cash-advances.deny', [$cashAdvance->employee->branch_id, $cashAdvance->employee->user?->id]);

        $cashAdvance->update(['status' => 'denied']);

        return back()->with('success', 'Cash advance denied.');
    }
}
