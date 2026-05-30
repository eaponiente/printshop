<?php

namespace Payroll\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\CashAdvance;
use App\Models\Payroll\CorrectionRequest;
use App\Models\Payroll\Employee;
use App\Models\Payroll\LeaveRequest;
use App\Models\Payroll\OvertimeRequest;
use App\Models\Payroll\TimeLog;
use Carbon\Carbon;
use DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Payroll\Attendance\Enums\PunchType;
use Payroll\Attendance\Services\TimeLogService;

class TimeLogController extends Controller
{
    use AuthorizesRequests;

    protected function findEmployeeForUser(): ?Employee
    {
        $user = auth()->user();

        if ($user->employee_id) {
            return Employee::with('branch')->find($user->employee_id);
        }

        return null;
    }

    public function index(Request $request, TimeLogService $service)
    {
        $employee = $this->findEmployeeForUser();

        $punchState = $employee
            ? $service->punchSequenceForDate($employee, now()->toDateString())
            : null;

        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $weekEnd = Carbon::now()->endOfWeek(Carbon::SUNDAY)->toDateString();

        return Inertia::render('payroll/attendance/my-attendance', [
            'tab' => $request->input('tab', 'punch'),
            'punchState' => $punchState,
            'employee' => $employee,
            'activeSchedule' => $employee?->activeSchedule(),
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'weekSheets' => Inertia::lazy(fn () => $employee
                ? AttendanceSheet::where('employee_id', $employee->id)
                    ->whereBetween('date', [$weekStart, $weekEnd])
                    ->orderBy('date')
                    ->get()
                : collect()),
            'recentTimeLogs' => Inertia::lazy(fn () => $employee
                ? TimeLog::where('employee_id', $employee->id)
                    ->whereBetween('timestamp', [$weekStart.' 00:00:00', $weekEnd.' 23:59:59'])
                    ->whereNull('duplicate_of')
                    ->orderBy('timestamp', 'desc')
                    ->limit(50)
                    ->get()
                : collect()),
            'recentOvertime' => Inertia::lazy(fn () => $employee
                ? OvertimeRequest::where('employee_id', $employee->id)
                    ->latest()
                    ->limit(10)
                    ->get(['id', 'date', 'hours_needed', 'shift_type', 'reason', 'status', 'created_at'])
                : collect()),
            'recentLeaves' => Inertia::lazy(fn () => $employee
                ? LeaveRequest::where('employee_id', $employee->id)
                    ->latest()
                    ->limit(10)
                    ->get(['id', 'date', 'leave_type', 'duration', 'is_paid', 'reason', 'status', 'created_at'])
                : collect()),
            'recentCorrections' => Inertia::lazy(fn () => $employee
                ? CorrectionRequest::where('employee_id', $employee->id)
                    ->latest()
                    ->limit(10)
                    ->get(['id', 'date', 'correction_type', 'reason', 'status', 'created_at'])
                : collect()),
            'recentCashAdvances' => Inertia::lazy(fn () => $employee
                ? CashAdvance::where('employee_id', $employee->id)
                    ->latest()
                    ->limit(10)
                    ->get(['id', 'amount', 'remaining_balance', 'reason', 'status', 'created_at'])
                : collect()),
        ]);
    }

    public function punch(Request $request, TimeLogService $service)
    {
        $employee = $this->findEmployeeForUser();

        if (! $employee) {
            return back()->withErrors(['error' => 'No employee record linked to your account.']);
        }

        $type = PunchType::from($request->input('type'));
        Gate::authorize('time-logs.punch', [$employee->branch_id]);

        $manualTimestamp = $request->input('manual_timestamp');

        try {
            $log = DB::transaction(function () use ($service, $employee, $type, $manualTimestamp) {
                return $service->punch($employee, $type, auth()->user(), $manualTimestamp);
            });

            if ($log->duplicate_of) {
                return back()->with('warning', 'Duplicate punch detected. Your earlier punch was kept.');
            }

            return back()->with('success', $type->label().' recorded at '.$log->timestamp->format('h:i A').'.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to record punch. Please try again.']);
        }
    }

    public function manual(Request $request, TimeLogService $service)
    {
        $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'type' => ['required', 'string', 'in:in,lunch_out,lunch_in,out'],
            'timestamp' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $employee = Employee::findOrFail($request->input('employee_id'));
        Gate::authorize('time-logs.manual', [$employee->branch_id]);

        try {
            $log = $service->manualLog($employee, $request->only(['type', 'timestamp', 'note']));

            return back()->with('success', 'Manual log recorded successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to record manual log.']);
        }
    }
}
