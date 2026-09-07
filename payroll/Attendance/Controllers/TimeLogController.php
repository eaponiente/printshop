<?php

namespace Payroll\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\Attendance\ManualTimeLogRequest;
use App\Http\Requests\Payroll\Attendance\PunchRequest;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\Employee;
use App\Models\Payroll\TimeLog;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
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

        $attendanceSheets = $employee
            ? AttendanceSheet::where('employee_id', $employee->id)
                ->where('date', '>=', Carbon::now()->subMonths(2)->startOfMonth()->toDateString())
                ->orderBy('date', 'desc')
                ->paginate(30)
            : null;

        $recentTimeLogs = $employee
            ? TimeLog::where('employee_id', $employee->id)
                ->where('timestamp', '>=', Carbon::now()->subDays(10)->startOfDay())
                ->whereNull('duplicate_of')
                ->orderBy('timestamp', 'desc')
                ->limit(60)
                ->get()
            : collect();

        return Inertia::render('payroll/attendance/my-attendance', [
            'punchState' => $punchState,
            'employee' => $employee,
            'activeSchedule' => $employee?->activeSchedule(),
            'serverNow' => now()->toIso8601String(),
            'attendanceSheets' => $attendanceSheets,
            'recentTimeLogs' => $recentTimeLogs,
        ]);
    }

    public function punch(PunchRequest $request, TimeLogService $service)
    {
        $employee = $this->findEmployeeForUser();

        if (! $employee) {
            return back()->withErrors(['error' => 'No employee record linked to your account.']);
        }

        Gate::authorize('time-logs.punch', [$employee->branch_id]);

        $type = $request->punchType();

        try {
            $log = $service->punch(
                $employee,
                $type,
                $request->user(),
                $request->input('latitude'),
                $request->input('longitude'),
                $request->input('accuracy_meters'),
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Punch failed', [
                'employee_id' => $employee->id,
                'type' => $type->value,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to record punch. Please try again.']);
        }

        if ($log->duplicate_of) {
            return back()->with('warning', 'Duplicate punch detected. Your earlier punch was kept.');
        }

        return back()->with('success', $type->label().' recorded at '.$log->timestamp->format('h:i A').'.');
    }

    public function manual(ManualTimeLogRequest $request, TimeLogService $service)
    {
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
