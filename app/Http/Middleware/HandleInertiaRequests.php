<?php

namespace App\Http\Middleware;

use App\Models\Payroll\CashAdvance;
use App\Models\Payroll\CorrectionRequest;
use App\Models\Payroll\LeaveRequest;
use App\Models\Payroll\OvertimeRequest;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user()
                    ? $request->user()->load('branch', 'employee')
                    : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'pending_requests' => $this->pendingRequests($request),
            'flash' => [
                'new_customer' => $request->session()->get('new_customer'),
                'message' => $request->session()->get('message'),
                'payslip_id' => $request->session()->get('payslip_id'),
            ],
        ];
    }

    protected function pendingRequests(Request $request): array
    {
        $defaults = [
            'overtime' => 0,
            'leave' => 0,
            'correction' => 0,
            'cash_advance' => 0,
        ];

        $user = $request->user();

        if (! $user) {
            return $defaults;
        }

        if (! in_array($user->role, ['admin', 'superadmin'], true)) {
            return $defaults;
        }

        $overtimeQuery = OvertimeRequest::where('status', 'pending');
        $leaveQuery = LeaveRequest::where('status', 'pending');
        $correctionQuery = CorrectionRequest::where('status', 'pending');
        $cashAdvanceQuery = CashAdvance::where('status', 'pending');

        if ($user->role === 'admin') {
            $branchId = $user->branch_id;
            $employeeId = $user->employee_id;

            $overtimeQuery->whereHas('employee', fn ($q) => $q->where('branch_id', $branchId));
            $leaveQuery->whereHas('employee', fn ($q) => $q->where('branch_id', $branchId));
            $correctionQuery->whereHas('employee', fn ($q) => $q->where('branch_id', $branchId));
            $cashAdvanceQuery->whereHas('employee', fn ($q) => $q->where('branch_id', $branchId));

            $overtimeQuery->whereDoesntHave('employee.user', fn ($q) => $q->where('role', 'superadmin'));
            $leaveQuery->whereDoesntHave('employee.user', fn ($q) => $q->where('role', 'superadmin'));
            $correctionQuery->whereDoesntHave('employee.user', fn ($q) => $q->where('role', 'superadmin'));
            $cashAdvanceQuery->whereDoesntHave('employee.user', fn ($q) => $q->where('role', 'superadmin'));

            if ($employeeId) {
                $overtimeQuery->where('employee_id', '!=', $employeeId);
                $leaveQuery->where('employee_id', '!=', $employeeId);
                $correctionQuery->where('employee_id', '!=', $employeeId);
                $cashAdvanceQuery->where('employee_id', '!=', $employeeId);
            }
        }

        return [
            'overtime' => $overtimeQuery->count(),
            'leave' => $leaveQuery->count(),
            'correction' => $correctionQuery->count(),
            'cash_advance' => $cashAdvanceQuery->count(),
        ];
    }
}
