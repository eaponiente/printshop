<?php

namespace Payroll\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payroll\CorrectionRequest;
use App\Models\Payroll\CorrectionRequestItem;
use App\Models\Payroll\Employee;
use App\Models\Payroll\TimeLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $query = CorrectionRequest::with(['employee:id,first_name,last_name,branch_id', 'employee.branch:id,name', 'items']);
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.punch_type' => ['required', 'string', 'in:in,out'],
            'items.*.requested_time' => ['required', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $punchTypes = array_column($validated['items'], 'punch_type');
        $inCount = count(array_keys($punchTypes, 'in'));
        $outCount = count(array_keys($punchTypes, 'out'));

        if ($inCount > 1 || $outCount > 1) {
            return back()->withErrors(['error' => 'You can only have one IN and one OUT per correction request.']);
        }

        $existing = CorrectionRequest::where('employee_id', $employee->id)
            ->where('date', $validated['date'])
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($existing) {
            return back()->withErrors(['error' => 'A correction request already exists for this date.']);
        }

        DB::transaction(function () use ($employee, $validated) {
            $correction = CorrectionRequest::create([
                'employee_id' => $employee->id,
                'date' => $validated['date'],
                'correction_type' => $validated['correction_type'],
                'reason' => $validated['reason'],
                'status' => 'pending',
            ]);

            foreach ($validated['items'] as $item) {
                CorrectionRequestItem::create([
                    'correction_request_id' => $correction->id,
                    'punch_type' => $item['punch_type'],
                    'requested_time' => $validated['date'].' '.$item['requested_time'].':00',
                ]);
            }
        });

        return back()->with('success', 'Correction request submitted.');
    }

    public function approve(CorrectionRequest $correction)
    {
        Gate::authorize('correction-requests.approve', [$correction->employee->branch_id, $correction->employee->user?->id]);

        $firstLogId = null;

        DB::transaction(function () use ($correction, &$firstLogId) {
            foreach ($correction->items as $item) {
                $log = TimeLog::create([
                    'employee_id' => $correction->employee_id,
                    'type' => PunchType::from($item->punch_type),
                    'source' => PunchSource::CORRECTION,
                    'timestamp' => $item->requested_time,
                ]);

                if ($firstLogId === null) {
                    $firstLogId = $log->id;
                }
            }

            app(AttendanceService::class)->processDailyAttendance(
                $correction->employee,
                $correction->date->toDateString(),
            );
        });

        $correction->update([
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'resolved_time_log_id' => $firstLogId,
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
