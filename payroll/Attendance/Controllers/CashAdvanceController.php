<?php

namespace Payroll\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Payroll\CashAdvance;
use App\Models\Payroll\Employee;
use App\Services\Sales\CashOnHandService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CashAdvanceController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $user = auth()->user();

        if ($user->isStaff()) {
            return redirect()->route('payroll.index');
        }

        $query = CashAdvance::with(['employee:id,first_name,last_name,branch_id', 'employee.branch:id,name', 'approvedBy:id,first_name,last_name']);

        if ($user->isAdmin()) {
            $query->whereHas('employee', fn ($q) => $q->where('branch_id', $user->branch_id));
            $query->whereDoesntHave('employee.user', fn ($q) => $q->where('role', 'superadmin'));
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(20);

        $employees = $user->isSuperAdmin()
            ? Employee::where('status', 'active')->with('branch:id,name')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'branch_id'])
            : Employee::where('branch_id', $user->branch_id)->where('status', 'active')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'branch_id']);

        return Inertia::render('payroll/requests/cash-advances', [
            'requests' => $requests,
            'employees' => $employees,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if ($user->isStaff()) {
            abort(403);
        }

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'amount'      => ['required', 'numeric', 'min:1'],
            'reason'      => ['required', 'string', 'max:500'],
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);

        if ($user->isAdmin() && (int) $employee->branch_id !== (int) $user->branch_id) {
            abort(403);
        }

        $activeCA = CashAdvance::where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved', 'unpaid'])
            ->where('remaining_balance', '>', 0)
            ->exists();

        if ($activeCA) {
            return back()->withErrors(['error' => 'This employee already has an unpaid cash advance.']);
        }

        DB::transaction(function () use ($validated, $employee, $user) {
            CashAdvance::create([
                'employee_id'       => $employee->id,
                'amount'            => $validated['amount'],
                'remaining_balance' => $validated['amount'],
                'reason'            => $validated['reason'],
                'status'            => 'approved',
                'approved_by'       => $user->id,
                'approved_at'       => now(),
            ]);

            Expense::create([
                'description'  => 'Cash Advance - ' . $employee->last_name . ', ' . $employee->first_name,
                'amount'       => $validated['amount'],
                'payment_type' => 'cash',
                'status'       => 'paid',
                'user_id'      => $user->id,
                'branch_id'    => $employee->branch_id,
                'expense_date' => now()->toDateString(),
            ]);

            app(CashOnHandService::class)->adjustBalance(
                $employee->branch_id,
                $validated['amount'],
                'expense'
            );
        });

        return back()->with('success', 'Cash advance created.');
    }

    public function approve(CashAdvance $cashAdvance)
    {
        Gate::authorize('cash-advances.approve', [$cashAdvance->employee->branch_id, $cashAdvance->employee->user?->id]);

        $cashAdvance->update([
            'status'      => 'approved',
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
