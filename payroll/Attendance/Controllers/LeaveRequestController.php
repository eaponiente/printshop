<?php

namespace Payroll\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\Employee;
use App\Models\Payroll\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Payroll\Attendance\Services\AttendanceService;
use Payroll\Audit\Traits\Auditable;

class LeaveRequestController extends Controller
{
    use Auditable;
    use AuthorizesRequests;

    public function index(): Response
    {
        $query = LeaveRequest::with(['employee:id,first_name,last_name,branch_id,default_paid_leave_days,paid_leave_balance', 'employee.branch:id,name']);
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

        // Pending requests always surface on top (they need action), then the
        // rest by leave date, most recent first. CASE keeps this SQLite-safe.
        $requests = $query
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('date', 'desc')
            ->paginate(20);

        $employeeSummary = null;
        if ($user->isStaff() && $user->employee_id) {
            $emp = Employee::find($user->employee_id);
            if ($emp) {
                $approvedPaidCount = LeaveRequest::where('employee_id', $emp->id)
                    ->where('status', 'approved')
                    ->where('is_paid', true)
                    ->count();
                $employeeSummary = [
                    'default_paid_leave_days' => (float) $emp->default_paid_leave_days,
                    'paid_leave_balance' => (float) $emp->paid_leave_balance,
                    'used_paid_leave_days' => $approvedPaidCount,
                ];
            }
        }

        $canReset = auth()->user()->isSuperAdmin()
            && Carbon::now()->month === 1
            && Carbon::now()->day <= 15;

        return Inertia::render('payroll/requests/leaves', [
            'requests' => $requests,
            'employeeSummary' => $employeeSummary,
            'canResetLeaves' => $canReset,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = Employee::findOrFail($request->user()->employee_id);
        Gate::authorize('leave-requests.submit', [$employee->branch_id]);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'leave_type' => ['required', 'string', 'in:vacation,sick,emergency,maternity,paternity,bereavement,unpaid'],
            'duration' => ['required', 'string', 'in:full_day'],
            'is_paid' => ['boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        LeaveRequest::create([
            'employee_id' => $employee->id,
            ...$validated,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Leave request submitted.');
    }

    public function approve(LeaveRequest $leaveRequest): RedirectResponse
    {
        Gate::authorize('leave-requests.approve', [$leaveRequest->employee->branch_id, $leaveRequest->employee->user?->id]);

        $lockedSheet = AttendanceSheet::where('employee_id', $leaveRequest->employee_id)
            ->where('date', $leaveRequest->date->toDateString())
            ->whereNotNull('locked_at')
            ->first();

        if ($lockedSheet) {
            throw ValidationException::withMessages([
                'error' => 'Attendance sheet for this date is locked in a payroll period.',
            ]);
        }

        $employee = $leaveRequest->employee;
        $isPaid = false;

        if ((float) $employee->paid_leave_balance >= 1) {
            $isPaid = true;
            $employee->decrement('paid_leave_balance', 1);
        }

        $leaveRequest->update([
            'status' => 'approved',
            'is_paid' => $isPaid,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        app(AttendanceService::class)->processDailyAttendance(
            $leaveRequest->employee,
            $leaveRequest->date->toDateString(),
        );

        $message = $isPaid
            ? 'Leave request approved.'
            : 'Leave request approved (unpaid — no remaining leave balance).';

        return back()->with('success', $message);
    }

    public function deny(LeaveRequest $leaveRequest): RedirectResponse
    {
        Gate::authorize('leave-requests.deny', [$leaveRequest->employee->branch_id, $leaveRequest->employee->user?->id]);

        if ($leaveRequest->status === 'approved' && $leaveRequest->is_paid) {
            $leaveRequest->employee->increment('paid_leave_balance', 1);
            $leaveRequest->update(['status' => 'denied', 'is_paid' => false]);
        } else {
            $leaveRequest->update(['status' => 'denied']);
        }

        return back()->with('success', 'Leave request denied.');
    }

    public function cancel(LeaveRequest $leaveRequest): RedirectResponse
    {
        Gate::authorize('leave-requests.cancel', [$leaveRequest->employee->branch_id, $leaveRequest->employee->user?->id]);

        if ($leaveRequest->status !== 'pending') {
            return back()->withErrors(['error' => 'Only pending leave requests can be cancelled.']);
        }

        $before = $leaveRequest->getAttributes();
        $leaveRequest->update(['status' => 'cancelled']);
        $after = $leaveRequest->getAttributes();

        $this->audit('cancelled', $leaveRequest, $before, $after);

        return back()->with('success', 'Leave request cancelled.');
    }

    public function destroy(LeaveRequest $leaveRequest): RedirectResponse
    {
        Gate::authorize('leave-requests.delete', [$leaveRequest->employee->branch_id, $leaveRequest->employee->user?->id]);

        if (! in_array($leaveRequest->status, ['pending', 'approved'], true)) {
            return back()->withErrors(['error' => 'Only pending or approved leave requests can be deleted.']);
        }

        $employee = $leaveRequest->employee;
        $date = $leaveRequest->date->toDateString();
        $wasApproved = $leaveRequest->status === 'approved';
        $wasApprovedPaid = $wasApproved && $leaveRequest->is_paid;

        if ($wasApproved) {
            $lockedSheet = AttendanceSheet::where('employee_id', $leaveRequest->employee_id)
                ->where('date', $date)
                ->whereNotNull('locked_at')
                ->first();

            if ($lockedSheet) {
                throw ValidationException::withMessages([
                    'error' => 'Attendance sheet for this date is locked in a payroll period.',
                ]);
            }
        }

        $before = $leaveRequest->getAttributes();

        DB::transaction(function () use ($leaveRequest, $employee, $wasApproved, $wasApprovedPaid, $date) {
            if ($wasApprovedPaid) {
                $employee->increment('paid_leave_balance', 1);
            }

            $leaveRequest->delete();

            if ($wasApproved) {
                app(AttendanceService::class)->processDailyAttendance($employee, $date);
            }
        });

        $this->audit('deleted', $leaveRequest, $before, []);

        return back()->with('success', 'Leave request deleted.');
    }

    public function resetLeave(): RedirectResponse
    {
        if (! auth()->user()->isSuperAdmin()) {
            abort(403);
        }

        $now = Carbon::now();
        if ($now->month !== 1 || $now->day > 15) {
            return back()->withErrors(['error' => 'Leave balances can only be reset between January 1 and 15.']);
        }

        $count = Employee::where('status', 'active')
            ->whereNotNull('default_paid_leave_days')
            ->count();

        Employee::where('status', 'active')
            ->whereNotNull('default_paid_leave_days')
            ->update(['paid_leave_balance' => DB::raw('default_paid_leave_days')]);

        return back()->with('success', "Leave balances reset for {$count} active employees.");
    }
}
