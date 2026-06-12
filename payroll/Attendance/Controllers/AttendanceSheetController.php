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
}
