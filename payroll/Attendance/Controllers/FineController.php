<?php

namespace Payroll\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payroll\AttendanceSheet;
use App\Models\Payroll\Employee;
use App\Models\Payroll\Fine;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Payroll\Attendance\Requests\StoreFineRequest;
use Payroll\Attendance\Services\FineService;

class FineController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreFineRequest $request, FineService $service)
    {
        $employee = Employee::findOrFail($request->input('employee_id'));
        $date = $request->input('date');

        $lockedSheet = AttendanceSheet::where('employee_id', $employee->id)
            ->where('date', $date)
            ->whereNotNull('locked_at')
            ->first();

        if ($lockedSheet) {
            throw ValidationException::withMessages([
                'error' => 'Attendance sheet for this date is locked in a payroll period.',
            ]);
        }

        $service->mark(
            $employee,
            $date,
            $request->input('fine_type'),
            (float) $request->input('amount'),
            $request->input('note'),
        );

        return back()->with('success', 'Fine marked successfully.');
    }

    public function destroy(Fine $fine, FineService $service)
    {
        $lockedSheet = AttendanceSheet::where('employee_id', $fine->employee_id)
            ->where('date', $fine->date->toDateString())
            ->whereNotNull('locked_at')
            ->first();

        if ($lockedSheet) {
            throw ValidationException::withMessages([
                'error' => 'Attendance sheet for this date is locked in a payroll period.',
            ]);
        }

        $service->remove($fine);

        return back()->with('success', 'Fine removed.');
    }
}
