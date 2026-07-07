<?php

namespace Payroll\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Payroll\Attendance\Requests\WorkWeekTableRequest;
use Payroll\Attendance\Services\WorkWeekTableService;

class WorkWeekTableController extends Controller
{
    public function index(WorkWeekTableRequest $request, WorkWeekTableService $service)
    {
        $user = auth()->user();
        $branch = $this->resolveBranch($request);

        Gate::authorize('work-week.view', [$branch->id]);

        [$startDate, $endDate] = $this->resolveDateRange($request);

        $page = $service->buildPage($branch, $startDate, $endDate);
        $full = $service->buildFull($branch, $startDate, $endDate);

        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : [];

        return Inertia::render('payroll/work-week/index', [
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'branches' => $branches,
            'isSuperAdmin' => $user->isSuperAdmin(),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dayColumns' => $this->dayColumns($startDate),
            'employees' => $page['employees'],
            'cells' => $page['cells'],
            'rowTotals' => $page['rowTotals'],
            'footerTotals' => $full['footerTotals'],
        ]);
    }

    public function print(WorkWeekTableRequest $request, WorkWeekTableService $service)
    {
        $branch = $this->resolveBranch($request);

        Gate::authorize('work-week.view', [$branch->id]);

        [$startDate, $endDate] = $this->resolveDateRange($request);

        $full = $service->buildFull($branch, $startDate, $endDate);

        return Inertia::render('payroll/work-week/print', [
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dayColumns' => $this->dayColumns($startDate),
            'employees' => $full['employees']->map(fn ($employee) => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'full_name' => $employee->full_name,
            ]),
            'cells' => $full['cells'],
            'rowTotals' => $full['rowTotals'],
            'footerTotals' => $full['footerTotals'],
        ]);
    }

    protected function resolveBranch(WorkWeekTableRequest $request): Branch
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            if ($request->filled('branch_id')) {
                return Branch::findOrFail($request->validated('branch_id'));
            }

            return Branch::orderBy('name')->firstOrFail();
        }

        return Branch::findOrFail($user->branch_id);
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveDateRange(WorkWeekTableRequest $request): array
    {
        if ($request->filled('start_date') && $request->filled('end_date')) {
            return [$request->validated('start_date'), $request->validated('end_date')];
        }

        $today = Carbon::today();
        $lastSaturday = $today->dayOfWeek === Carbon::SATURDAY ? $today->copy() : $today->copy()->previous(Carbon::SATURDAY);

        return [$lastSaturday->toDateString(), $lastSaturday->copy()->addDays(6)->toDateString()];
    }

    /**
     * The 6 columns shown on the grid: Saturday, then Monday through Friday of
     * the same payroll week. Sunday is intentionally excluded as a column
     * (universal rest day) though its data still feeds the row/footer totals.
     *
     * @return array<int, string>
     */
    protected function dayColumns(string $startDate): array
    {
        $saturday = Carbon::parse($startDate);

        return [
            $saturday->toDateString(),
            $saturday->copy()->addDays(2)->toDateString(),
            $saturday->copy()->addDays(3)->toDateString(),
            $saturday->copy()->addDays(4)->toDateString(),
            $saturday->copy()->addDays(5)->toDateString(),
            $saturday->copy()->addDays(6)->toDateString(),
        ];
    }
}
