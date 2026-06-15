<?php

namespace Payroll\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\Employee;
use App\Models\Payroll\LeaveRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Payroll\Attendance\Services\AttendanceService;
use Payroll\Audit\Traits\Auditable;

class LeaveRequestController extends Controller
{
    use Auditable;
    use AuthorizesRequests;

    public function index()
    {
        $query = LeaveRequest::with(['employee:id,first_name,last_name,branch_id', 'employee.branch:id,name']);
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

        return Inertia::render('payroll/requests/leaves', [
            'requests' => $requests,
        ]);
    }

    public function store(Request $request)
    {
        $employee = Employee::findOrFail($request->user()->employee_id);
        Gate::authorize('leave-requests.submit', [$employee->branch_id]);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'leave_type' => ['required', 'string', 'in:vacation,sick,emergency,maternity,paternity,bereavement,unpaid'],
            'duration' => ['required', 'string', 'in:full_day,half_day_morning,half_day_afternoon'],
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

    public function approve(LeaveRequest $leaveRequest)
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

        $leaveRequest->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        app(AttendanceService::class)->processDailyAttendance(
            $leaveRequest->employee,
            $leaveRequest->date->toDateString(),
        );

        return back()->with('success', 'Leave request approved.');
    }

    public function deny(LeaveRequest $leaveRequest)
    {
        Gate::authorize('leave-requests.deny', [$leaveRequest->employee->branch_id, $leaveRequest->employee->user?->id]);

        $leaveRequest->update(['status' => 'denied']);

        return back()->with('success', 'Leave request denied.');
    }

    public function cancel(LeaveRequest $leaveRequest)
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
}
