<?php

namespace Payroll\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\Employee;
use App\Models\Payroll\TimeLog;
use Carbon\Carbon;
use DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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

        $today = now()->toDateString();
        $lockedSheet = AttendanceSheet::where('employee_id', $employee->id)
            ->where('date', $today)
            ->whereNotNull('locked_at')
            ->first();

        if ($lockedSheet) {
            throw ValidationException::withMessages([
                'error' => 'Attendance sheet for this date is locked in a payroll period.',
            ]);
        }

        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $accuracyMeters = $request->input('accuracy_meters');

        try {
            $log = DB::transaction(function () use ($service, $employee, $type, $latitude, $longitude, $accuracyMeters) {
                return $service->punch($employee, $type, auth()->user(), $latitude, $longitude, $accuracyMeters);
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

        $date = Carbon::parse($request->input('timestamp'))->toDateString();
        $lockedSheet = AttendanceSheet::where('employee_id', $employee->id)
            ->where('date', $date)
            ->whereNotNull('locked_at')
            ->first();

        if ($lockedSheet) {
            throw ValidationException::withMessages([
                'error' => 'Attendance sheet for this date is locked in a payroll period.',
            ]);
        }

        try {
            $log = $service->manualLog($employee, $request->only(['type', 'timestamp', 'note']));

            return back()->with('success', 'Manual log recorded successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to record manual log.']);
        }
    }
}
