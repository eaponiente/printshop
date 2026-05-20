<?php

namespace App\Providers;

use App\Models\Expense;
use App\Models\Payroll\Employee;
use App\Policies\ExpensePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Payroll\Audit\Models\AuditLog;
use Payroll\Audit\Policies\AuditLogPolicy;
use Payroll\Employee\Policies\EmployeePolicy as PayrollEmployeePolicy;

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

        Gate::policy(Expense::class, ExpensePolicy::class);
        Gate::policy(Employee::class, PayrollEmployeePolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
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
