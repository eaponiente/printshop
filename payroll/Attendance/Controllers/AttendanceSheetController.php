<?php

namespace Payroll\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\Employee;
use App\Models\Payroll\Fine;
use App\Models\Payroll\TimeLog;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Payroll\Attendance\Enums\PunchType;

class AttendanceSheetController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $user = auth()->user();

        $date = $request->query('date', now()->toDateString());
        $weekStart = Carbon::parse($date)->startOfWeek(Carbon::MONDAY)->toDateString();
        $weekEnd = Carbon::parse($date)->endOfWeek(Carbon::SUNDAY)->toDateString();

        $employeeQuery = Employee::query()->where('status', 'active');

        $selectedBranch = null;

        if ($user->isStaff()) {
            $employeeQuery->where('id', $user->employee_id);
        } elseif ($user->isAdmin()) {
            $employeeQuery->where('branch_id', $user->branch_id);
        } elseif ($user->isSuperAdmin()) {
            if ($request->filled('branch_id')) {
                $employeeQuery->where('branch_id', $request->input('branch_id'));
                $selectedBranch = (int) $request->input('branch_id');
            }
        }

        $employees = $employeeQuery->orderBy('last_name')->simplePaginate(50);

        $sheets = AttendanceSheet::whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->get()
            ->keyBy(function ($sheet) {
                return $sheet->employee_id.'-'.$sheet->date->format('Y-m-d');
            });

        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : [];

        return Inertia::render('payroll/attendance/sheets', [
            'employees' => $employees,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'currentDate' => $date,
            'sheets' => $sheets,
            'branches' => $branches,
            'selectedBranch' => $selectedBranch,
            'isSuperAdmin' => $user->isSuperAdmin(),
        ]);
    }

    public function show(Employee $employee, Request $request)
    {
        $user = auth()->user();

        if ($user->isStaff()) {
            Gate::authorize('attendance-sheets.viewOwn', [$employee->id, $user->employee_id]);
        } else {
            Gate::authorize('attendance-sheets.show', [$employee->branch_id]);
        }

        $date = $request->query('date', now()->toDateString());

        $sheet = AttendanceSheet::where('employee_id', $employee->id)
            ->where('date', $date)
            ->first();

        $fines = Fine::where('employee_id', $employee->id)
            ->where('date', $date)
            ->orderBy('created_at')
            ->get();

        $timeLogs = TimeLog::where('employee_id', $employee->id)
            ->whereBetween('timestamp', [$date.' 00:00:00', $date.' 23:59:59'])
            ->whereNull('duplicate_of')
            ->orderBy('timestamp')
            ->get();

        return Inertia::render('payroll/attendance/sheet-detail', [
            'employee' => $employee->load('branch'),
            'date' => $date,
            'sheet' => $sheet,
            'fines' => $fines,
            'timeLogs' => $timeLogs,
        ]);
    }

    public function geo(Request $request)
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin()) {
            abort(403);
        }

        $branchId = $request->query('branch_id');
        $dateFrom = $request->query('date_from', now()->startOfWeek()->toDateString());
        $dateTo = $request->query('date_to', now()->toDateString());

        $branches = Branch::orderBy('name')->get(['id', 'name', 'latitude', 'longitude', 'geofence_radius']);

        $branch = null;
        if ($branchId) {
            $branch = $branches->firstWhere('id', (int) $branchId);
        }

        $query = TimeLog::with(['employee:id,first_name,last_name,branch_id', 'employee.branch:id,name'])
            ->whereIn('type', [PunchType::IN->value, PunchType::OUT->value])
            ->whereBetween('timestamp', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59'])
            ->whereNull('duplicate_of');

        if ($branchId) {
            $query->whereHas('employee', fn ($q) => $q->where('branch_id', $branchId));
        }

        $timeLogs = $query->orderBy('timestamp', 'desc')->paginate(50);

        $timeLogs->getCollection()->transform(function ($log) use ($branch) {
            $distance = null;
            $proximity = null;

            if ($log->latitude !== null && $log->longitude !== null && $branch && $branch->latitude !== null && $branch->longitude !== null) {
                $distance = $this->haversineDistance((float) $log->latitude, (float) $log->longitude, (float) $branch->latitude, (float) $branch->longitude);
                $radius = $branch->geofence_radius ?? 100;
                $proximity = $distance <= $radius ? 'within' : 'outside';
            } elseif ($log->latitude === null || $log->longitude === null) {
                $proximity = 'no_location';
            } else {
                $proximity = 'no_branch_coords';
            }

            $log->distance = $distance;
            $log->proximity = $proximity;

            return $log;
        });

        return Inertia::render('payroll/attendance/geo', [
            'timeLogs' => $timeLogs,
            'branches' => $branches,
            'selectedBranch' => $branchId ? (int) $branchId : null,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $earthRadius = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round($earthRadius * $c);
    }
}
