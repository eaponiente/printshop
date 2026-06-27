<?php

namespace Payroll\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payroll\PayrollPeriod;
use App\Models\Payroll\PayrollPeriodItem;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Payroll\Attendance\Enums\PayrollPeriodStatus;
use Payroll\Attendance\Requests\BranchPayablesRequest;
use Payroll\Attendance\Requests\PrintPayslipsRequest;

class PayrollReportController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->isStaff()) {
            abort(403);
        }

        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : Branch::accessibleBy($user)->get(['id', 'name']);

        $periodsQuery = PayrollPeriod::query()
            ->whereIn('status', [PayrollPeriodStatus::APPROVED->value, PayrollPeriodStatus::PAID->value]);

        if (! $user->isSuperAdmin()) {
            $branchIds = $branches->pluck('id');
            $periodsQuery->whereIn('branch_id', $branchIds);
        }

        $periods = $periodsQuery
            ->with('branch:id,name')
            ->orderBy('period_start', 'desc')
            ->get(['id', 'branch_id', 'period_start', 'period_end', 'status']);

        return Inertia::render('payroll/reports/index', [
            'branches' => $branches,
            'periods' => $periods,
        ]);
    }

    public function print(PrintPayslipsRequest $request)
    {
        $user = auth()->user();

        if ($user->isStaff()) {
            abort(403);
        }

        $validated = $request->validated();
        $branchId = (int) $validated['branch_id'];
        $periodId = (int) $validated['period_id'];

        Gate::authorize('payroll-periods.view', [$branchId]);

        $period = PayrollPeriod::with('branch:id,name')->findOrFail($periodId);

        $items = PayrollPeriodItem::where('payroll_period_id', $periodId)
            ->with([
                'employee:id,first_name,last_name,employee_number,current_daily_rate,sss_number,philhealth_number,pagibig_number,tin_number,position',
            ])
            ->orderBy('id')
            ->get();

        return Inertia::render('payroll/reports/print', [
            'period' => [
                'id' => $period->id,
                'branch' => $period->branch->name,
                'period_start' => $period->period_start->format('Y-m-d'),
                'period_end' => $period->period_end->format('Y-m-d'),
                'status' => $period->status->label(),
            ],
            'items' => $items->map(fn (PayrollPeriodItem $item) => [
                'id' => $item->id,
                'employee' => [
                    'id' => $item->employee->id,
                    'first_name' => $item->employee->first_name,
                    'last_name' => $item->employee->last_name,
                    'employee_number' => $item->employee->employee_number,
                    'current_daily_rate' => (float) $item->employee->current_daily_rate,
                    'sss_number' => $item->employee->sss_number,
                    'philhealth_number' => $item->employee->philhealth_number,
                    'pagibig_number' => $item->employee->pagibig_number,
                    'tin_number' => $item->employee->tin_number,
                    'position' => $item->employee->position->value,
                ],
                'total_regular_days' => $item->total_regular_days,
                'absent_days' => $item->absent_days,
                'total_late_minutes' => $item->total_late_minutes,
                'late_deduction' => (float) $item->late_deduction,
                'total_undertime_minutes' => $item->total_undertime_minutes,
                'undertime_deduction' => (float) $item->undertime_deduction,
                'total_overtime_minutes' => $item->total_overtime_minutes,
                'overtime_pay' => (float) $item->overtime_pay,
                'holiday_pay_days' => $item->holiday_pay_days,
                'holiday_pay' => (float) $item->holiday_pay,
                'leave_paid_days' => $item->leave_paid_days,
                'fine_deduction' => (float) $item->fine_deduction,
                'gross_pay' => (float) $item->gross_pay,
                'deminimis_earnings' => (float) $item->deminimis_earnings,
                'sss_deduction' => (float) $item->sss_deduction,
                'philhealth_deduction' => (float) $item->philhealth_deduction,
                'pagibig_deduction' => (float) $item->pagibig_deduction,
                'ca_deduction' => (float) $item->ca_deduction,
                'net_pay' => (float) $item->net_pay,
                'daily_rate' => (float) $item->daily_rate,
                'sss_bracket' => $item->sss_bracket,
            ]),
        ]);
    }

    public function branchPayables(BranchPayablesRequest $request)
    {
        $user = auth()->user();
        $validated = $request->validated();

        $branches = $user->isSuperAdmin()
            ? Branch::orderBy('name')->get(['id', 'name'])
            : Branch::accessibleBy($user)->get(['id', 'name']);

        $branchIds = $branches->pluck('id');

        $periods = PayrollPeriod::query()
            ->whereIn('status', [PayrollPeriodStatus::APPROVED->value, PayrollPeriodStatus::PAID->value])
            ->whereIn('branch_id', $branchIds)
            ->with('branch:id,name')
            ->orderBy('period_start', 'desc')
            ->get(['id', 'branch_id', 'period_start', 'period_end', 'status']);

        $submittedIds = $validated['period_ids'] ?? [];

        if (empty($submittedIds)) {
            return Inertia::render('payroll/reports/branch-payables', [
                'periods' => $periods,
                'results' => [],
                'grand_total' => null,
                'filters' => $validated,
            ]);
        }

        $allowedIds = $periods->pluck('id')->all();
        $selectedIds = array_values(array_intersect($submittedIds, $allowedIds));

        $aggregated = PayrollPeriodItem::query()
            ->join('payroll_periods', 'payroll_period_items.payroll_period_id', '=', 'payroll_periods.id')
            ->whereIn('payroll_period_items.payroll_period_id', $selectedIds)
            ->select(
                'payroll_periods.id as period_id',
                'payroll_periods.branch_id',
                'payroll_periods.period_start',
                'payroll_periods.period_end',
                'payroll_periods.status',
                DB::raw('SUM(payroll_period_items.sss_deduction)        as total_sss_employee'),
                DB::raw('SUM(payroll_period_items.sss_employer)         as total_sss_employer'),
                DB::raw('SUM(payroll_period_items.philhealth_deduction) as total_philhealth_employee'),
                DB::raw('SUM(payroll_period_items.philhealth_employer)  as total_philhealth_employer'),
                DB::raw('SUM(payroll_period_items.pagibig_deduction)    as total_pagibig_employee'),
                DB::raw('SUM(payroll_period_items.pagibig_employer)     as total_pagibig_employer'),
            )
            ->groupBy(
                'payroll_periods.id',
                'payroll_periods.branch_id',
                'payroll_periods.period_start',
                'payroll_periods.period_end',
                'payroll_periods.status',
            )
            ->get();

        $branchMap = $branches->keyBy('id');

        $results = $aggregated->map(function ($row) use ($branchMap) {
            $sssEmployee = (float) $row->total_sss_employee;
            $sssEmployer = (float) $row->total_sss_employer;
            $phicEmployee = (float) $row->total_philhealth_employee;
            $phicEmployer = (float) $row->total_philhealth_employer;
            $hdmfEmployee = (float) $row->total_pagibig_employee;
            $hdmfEmployer = (float) $row->total_pagibig_employer;

            return [
                'period_id' => $row->period_id,
                'branch' => $branchMap[$row->branch_id]?->name ?? '—',
                'period_start' => $row->period_start instanceof Carbon
                    ? $row->period_start->format('Y-m-d')
                    : (string) $row->period_start,
                'period_end' => $row->period_end instanceof Carbon
                    ? $row->period_end->format('Y-m-d')
                    : (string) $row->period_end,
                'status' => $row->status instanceof PayrollPeriodStatus
                    ? $row->status->value
                    : (string) $row->status,
                'sss_employee' => $sssEmployee,
                'sss_employer' => $sssEmployer,
                'sss_total' => round($sssEmployee + $sssEmployer, 2),
                'philhealth_employee' => $phicEmployee,
                'philhealth_employer' => $phicEmployer,
                'philhealth_total' => round($phicEmployee + $phicEmployer, 2),
                'pagibig_employee' => $hdmfEmployee,
                'pagibig_employer' => $hdmfEmployer,
                'pagibig_total' => round($hdmfEmployee + $hdmfEmployer, 2),
            ];
        })->values();

        $grandTotal = [
            'sss_employee' => round($results->sum('sss_employee'), 2),
            'sss_employer' => round($results->sum('sss_employer'), 2),
            'sss_total' => round($results->sum('sss_total'), 2),
            'philhealth_employee' => round($results->sum('philhealth_employee'), 2),
            'philhealth_employer' => round($results->sum('philhealth_employer'), 2),
            'philhealth_total' => round($results->sum('philhealth_total'), 2),
            'pagibig_employee' => round($results->sum('pagibig_employee'), 2),
            'pagibig_employer' => round($results->sum('pagibig_employer'), 2),
            'pagibig_total' => round($results->sum('pagibig_total'), 2),
        ];

        return Inertia::render('payroll/reports/branch-payables', [
            'periods' => $periods,
            'results' => $results,
            'grand_total' => $grandTotal,
            'filters' => $validated,
        ]);
    }
}
