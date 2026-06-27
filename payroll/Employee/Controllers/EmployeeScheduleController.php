<?php

namespace Payroll\Employee\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payroll\Employee;
use App\Models\Payroll\EmployeeSchedule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Payroll\Audit\Traits\Auditable;
use Payroll\Employee\Requests\StoreEmployeeScheduleRequest;
use Payroll\Employee\Requests\UpdateEmployeeScheduleRequest;

class EmployeeScheduleController extends Controller
{
    use Auditable;
    use AuthorizesRequests;

    public function index(Employee $employee)
    {
        $this->authorize('view', $employee);

        $schedules = $employee->schedules()->paginate(20);

        return response()->json(['schedules' => $schedules]);
    }

    public function store(StoreEmployeeScheduleRequest $request, Employee $employee)
    {
        $validated = $request->validated();

        $schedule = $employee->schedules()->create(array_merge($validated, [
            'is_active' => $validated['is_active'] ?? true,
        ]));

        $this->audit('created', $schedule, [], $schedule->getAttributes());

        return back()->with('success', 'Schedule added successfully.');
    }

    public function update(UpdateEmployeeScheduleRequest $request, EmployeeSchedule $schedule)
    {
        $before = $schedule->getAttributes();

        $validated = $request->validated();

        $schedule->update($validated);
        $this->audit('updated', $schedule, $before, $schedule->getAttributes());

        return back()->with('success', 'Schedule updated successfully.');
    }

    public function destroy(EmployeeSchedule $schedule)
    {
        $this->authorize('update', $schedule->employee);

        $before = $schedule->getAttributes();
        $schedule->delete();
        $this->audit('deleted', $schedule, $before, []);

        return back()->with('success', 'Schedule removed successfully.');
    }
}
