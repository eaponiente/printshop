<?php

namespace App\Providers;

use App\Models\Expense;
use App\Models\Payroll\Employee;
use App\Models\Payroll\Holiday;
use App\Models\Payroll\PayrollPeriod;
use App\Policies\ExpensePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Payroll\Attendance\Policies\AttendanceSheetPolicy;
use Payroll\Attendance\Policies\CashAdvancePolicy;
use Payroll\Attendance\Policies\CompanyConfigPolicy;
use Payroll\Attendance\Policies\CorrectionRequestPolicy;
use Payroll\Attendance\Policies\FinePolicy;
use Payroll\Attendance\Policies\HolidayPolicy;
use Payroll\Attendance\Policies\LeaveRequestPolicy;
use Payroll\Attendance\Policies\OvertimeRequestPolicy;
use Payroll\Attendance\Policies\PayrollPeriodPolicy;
use Payroll\Attendance\Policies\TimeLogPolicy;
use Payroll\Audit\Models\AuditLog;
use Payroll\Audit\Policies\AuditLogPolicy;
use Payroll\Employee\Policies\EmployeePolicy as PayrollEmployeePolicy;
use Payroll\SewedItem\Policies\SewedItemPolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        $this->registerPolicies();
    }

    /**
     * Register all application and payroll attendance policies.
     */
    protected function registerPolicies(): void
    {
        // Existing policies
        Gate::policy(Expense::class, ExpensePolicy::class);
        Gate::policy(Employee::class, PayrollEmployeePolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Holiday::class, HolidayPolicy::class);
        Gate::policy(PayrollPeriod::class, PayrollPeriodPolicy::class);

        // Sewed Items
        Gate::define('sewed-items.viewAny', [SewedItemPolicy::class, 'viewAny']);
        Gate::define('sewed-items.create', [SewedItemPolicy::class, 'create']);
        Gate::define('sewed-items.update', [SewedItemPolicy::class, 'update']);
        Gate::define('sewed-items.delete', [SewedItemPolicy::class, 'delete']);

        // Custom action gates for payroll periods (not auto-registered by Gate::policy)
        Gate::define('payroll-periods.generate', [PayrollPeriodPolicy::class, 'generate']);
        Gate::define('payroll-periods.approve', [PayrollPeriodPolicy::class, 'approve']);
        Gate::define('payroll-periods.void', [PayrollPeriodPolicy::class, 'void']);
        Gate::define('payroll-periods.view', [PayrollPeriodPolicy::class, 'view']);
        Gate::define('payroll-periods.delete', [PayrollPeriodPolicy::class, 'delete']);

        // Payroll attendance policies — registered with action-based gates
        // until the corresponding Eloquent models are created.
        // When models exist, switch to Gate::policy(Model::class, Policy::class).
        // HolidayPolicy is registered via Gate::policy above.

        Gate::define('time-logs.punch', [TimeLogPolicy::class, 'punch']);
        Gate::define('time-logs.manual', [TimeLogPolicy::class, 'manualLog']);
        Gate::define('time-logs.viewAny', [TimeLogPolicy::class, 'viewAny']);

        Gate::define('overtime-requests.submit', [OvertimeRequestPolicy::class, 'submit']);
        Gate::define('overtime-requests.approve', [OvertimeRequestPolicy::class, 'approve']);
        Gate::define('overtime-requests.deny', [OvertimeRequestPolicy::class, 'deny']);

        Gate::define('leave-requests.submit', [LeaveRequestPolicy::class, 'submit']);
        Gate::define('leave-requests.approve', [LeaveRequestPolicy::class, 'approve']);
        Gate::define('leave-requests.deny', [LeaveRequestPolicy::class, 'deny']);
        Gate::define('leave-requests.cancel', [LeaveRequestPolicy::class, 'cancel']);

        Gate::define('correction-requests.submit', [CorrectionRequestPolicy::class, 'submit']);
        Gate::define('correction-requests.approve', [CorrectionRequestPolicy::class, 'approve']);
        Gate::define('correction-requests.deny', [CorrectionRequestPolicy::class, 'deny']);

        Gate::define('cash-advances.request', [CashAdvancePolicy::class, 'request']);
        Gate::define('cash-advances.approve', [CashAdvancePolicy::class, 'approve']);
        Gate::define('cash-advances.deny', [CashAdvancePolicy::class, 'deny']);

        Gate::define('company-config.edit', [CompanyConfigPolicy::class, 'edit']);

        Gate::define('fines.mark', [FinePolicy::class, 'mark']);
        Gate::define('fines.manage-types', [FinePolicy::class, 'manageTypes']);

        Gate::define('attendance-sheets.index', [AttendanceSheetPolicy::class, 'viewAny']);
        Gate::define('attendance-sheets.show', [AttendanceSheetPolicy::class, 'view']);
        Gate::define('attendance-sheets.viewOwn', [AttendanceSheetPolicy::class, 'viewOwn']);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn (): ?Password => app()->isProduction()
                ? Password::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
                : null,
        );
    }
}
