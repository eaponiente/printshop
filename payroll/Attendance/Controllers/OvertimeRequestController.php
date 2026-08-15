<?php

namespace Payroll\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\Employee;
use App\Models\Payroll\Holiday;
use App\Models\Payroll\OvertimeRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Payroll\Attendance\Services\AttendanceService;

class OvertimeRequestController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $query = OvertimeRequest::with(['employee:id,first_name,last_name,branch_id', 'employee.branch:id,name']);
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

        return Inertia::render('payroll/requests/overtime', [
            'requests' => $requests,
        ]);
    }

    public function store(Request $request)
    {
        $employee = Employee::findOrFail($request->user()->employee_id);
        Gate::authorize('overtime-requests.submit', [$employee->branch_id]);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $shiftType = $this->resolveShiftType($employee, $validated['date']);

        OvertimeRequest::create([
            'employee_id' => $employee->id,
            'date' => $validated['date'],
            'start_time' => $validated['date'].' '.$validated['start_time'].':00',
            'end_time' => $validated['date'].' '.$validated['end_time'].':00',
            'shift_type' => $shiftType,
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        return back()->with('success', 'OT request submitted.');
    }

    public function approve(OvertimeRequest $overtimeRequest)
    {
        Gate::authorize('overtime-requests.approve', [$overtimeRequest->employee->branch_id, $overtimeRequest->employee->user?->id]);

        $lockedSheet = AttendanceSheet::where('employee_id', $overtimeRequest->employee_id)
            ->where('date', $overtimeRequest->date->toDateString())
            ->whereNotNull('locked_at')
            ->first();

        if ($lockedSheet) {
            throw ValidationException::withMessages([
                'error' => 'Attendance sheet for this date is locked in a payroll period.',
            ]);
        }

        $overtimeRequest->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        app(AttendanceService::class)->processDailyAttendance(
            $overtimeRequest->employee,
            $overtimeRequest->date->toDateString(),
        );

        return back()->with('success', 'OT request approved.');
    }

    public function deny(OvertimeRequest $overtimeRequest)
    {
        Gate::authorize('overtime-requests.deny', [$overtimeRequest->employee->branch_id, $overtimeRequest->employee->user?->id]);

        $overtimeRequest->update(['status' => 'denied']);

        return back()->with('success', 'OT request denied.');
    }

    protected function resolveShiftType(Employee $employee, string $date): string
    {
        // Rest days are paid as regular days (no premium), so overtime on a rest
        // day resolves like a normal working day — holiday type still takes
        // precedence when a holiday falls on the date.
        $holiday = Holiday::forDate($date, $employee->branch_id);

        if ($holiday) {
            return $holiday->type->value === 'regular' ? 'regular_holiday' : 'special_holiday';
        }

        return 'regular_day';
    }
}
