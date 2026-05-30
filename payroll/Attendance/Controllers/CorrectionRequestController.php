<?php

namespace Payroll\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payroll\CorrectionRequest;
use App\Models\Payroll\Employee;
use App\Models\Payroll\TimeLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Payroll\Attendance\Enums\PunchSource;
use Payroll\Attendance\Enums\PunchType;
use Payroll\Attendance\Services\AttendanceService;

class CorrectionRequestController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $query = CorrectionRequest::with(['employee:id,first_name,last_name,branch_id', 'employee.branch:id,name']);
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

        return Inertia::render('payroll/requests/corrections', [
            'requests' => $requests,
        ]);
    }

    public function store(Request $request)
    {
        $employee = Employee::findOrFail($request->user()->employee_id);
        Gate::authorize('correction-requests.submit', [$employee->branch_id]);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'correction_type' => ['required', 'string', 'in:missed_punch_in,missed_punch_out,time_adjustment,absent_to_present'],
            'requested_time' => ['required_if:correction_type,missed_punch_in,missed_punch_out', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if (! empty($validated['requested_time'])) {
            $validated['requested_time'] = $validated['date'].' '.$validated['requested_time'].':00';
        }

        $existing = CorrectionRequest::where('employee_id', $employee->id)
            ->where('date', $validated['date'])
            ->where('correction_type', $validated['correction_type'])
            ->where('status', 'pending')
            ->exists();

        if ($existing) {
            return back()->withErrors(['error' => 'A pending request already exists for this date and type.']);
        }

        CorrectionRequest::create([
            'employee_id' => $employee->id,
            ...$validated,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Correction request submitted.');
    }

    public function approve(CorrectionRequest $correction)
    {
        Gate::authorize('correction-requests.approve', [$correction->employee->branch_id, $correction->employee->user?->id]);

        if ($correction->correction_type === 'missed_punch_in') {
            $log = TimeLog::create([
                'employee_id' => $correction->employee_id,
                'type' => PunchType::IN,
                'source' => PunchSource::CORRECTION,
                'timestamp' => $correction->requested_time ?? $correction->date.' 08:00:00',
            ]);
        } elseif ($correction->correction_type === 'missed_punch_out') {
            $log = TimeLog::create([
                'employee_id' => $correction->employee_id,
                'type' => PunchType::OUT,
                'source' => PunchSource::CORRECTION,
                'timestamp' => $correction->requested_time ?? $correction->date.' 17:00:00',
            ]);
        } elseif (in_array($correction->correction_type, ['time_adjustment', 'absent_to_present'])) {
            $log1 = TimeLog::create([
                'employee_id' => $correction->employee_id,
                'type' => PunchType::IN,
                'source' => PunchSource::CORRECTION,
                'timestamp' => $correction->requested_time ?? $correction->date.' 08:00:00',
            ]);
            TimeLog::create([
                'employee_id' => $correction->employee_id,
                'type' => PunchType::OUT,
                'source' => PunchSource::CORRECTION,
                'timestamp' => $correction->requested_time
                    ? date('Y-m-d H:i:s', strtotime($correction->requested_time) + 9 * 3600)
                    : $correction->date.' 17:00:00',
            ]);
            $log = $log1;
        }

        if (isset($log)) {
            app(AttendanceService::class)
                ->processDailyAttendance($correction->employee, $correction->date->toDateString());
        }

        $correction->update([
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'resolved_time_log_id' => $log->id ?? null,
        ]);

        return back()->with('success', 'Correction approved.');
    }

    public function deny(Request $request, CorrectionRequest $correction)
    {
        Gate::authorize('correction-requests.deny', [$correction->employee->branch_id, $correction->employee->user?->id]);

        $correction->update([
            'status' => 'denied',
            'denial_reason' => $request->input('denial_reason'),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Correction denied.');
    }
}
