<?php

namespace Payroll\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payroll\Employee;
use App\Models\Payroll\Fine;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Payroll\Attendance\Requests\StoreFineRequest;
use Payroll\Attendance\Services\FineService;

class FineController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreFineRequest $request, FineService $service)
    {
        $employee = Employee::findOrFail($request->input('employee_id'));

        $service->mark(
            $employee,
            $request->input('date'),
            $request->input('fine_type'),
            (float) $request->input('amount'),
            $request->input('note'),
        );

        return back()->with('success', 'Fine marked successfully.');
    }

    public function destroy(Fine $fine, FineService $service)
    {
        $service->remove($fine);

        return back()->with('success', 'Fine removed.');
    }
}
