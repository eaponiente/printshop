<?php

use App\Http\Controllers\Home\DashboardController;
use App\Http\Controllers\Incentives\IncentiveController;
use App\Http\Controllers\Payroll\Settings\PayrollSettingController;
use App\Http\Controllers\PurchaseOrders\PurchaseOrderController;
use App\Http\Controllers\ReleaseNotesController;
use App\Http\Controllers\Sales\ExpenseController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\TagController;
use App\Http\Controllers\Sublimations\SublimationController;
use App\Http\Controllers\Sublimations\SublimationImageController;
use App\Http\Controllers\Sublimations\SublimationTagController;
use App\Http\Controllers\Users\BranchController;
use App\Http\Controllers\Users\CustomerController;
use App\Http\Controllers\Users\EndorsementController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Payroll\Attendance\Controllers\AttendanceSheetController;
use Payroll\Attendance\Controllers\CashAdvanceController;
use Payroll\Attendance\Controllers\CompanyConfigController;
use Payroll\Attendance\Controllers\CorrectionRequestController;
use Payroll\Attendance\Controllers\FineController;
use Payroll\Attendance\Controllers\HolidayController;
use Payroll\Attendance\Controllers\LeaveRequestController;
use Payroll\Attendance\Controllers\OvertimeRequestController;
use Payroll\Attendance\Controllers\PayrollPeriodController;
use Payroll\Attendance\Controllers\PayrollReportController;
use Payroll\Attendance\Controllers\SssBracketController;
use Payroll\Attendance\Controllers\TimeLogController;
use Payroll\Attendance\Controllers\WorkWeekTableController;
use Payroll\Audit\Controllers\AuditLogController;
use Payroll\Employee\Controllers\EmployeeController;
use Payroll\Employee\Controllers\EmployeeScheduleController;
use Payroll\SewedItem\Controllers\SewedItemController;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('release-notes', [ReleaseNotesController::class, 'index'])->name('release-notes.index');
    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::resource('branches', BranchController::class);
    Route::resource('tags', TagController::class);

    Route::get('/sublimation-tags', [SublimationTagController::class, 'index']);
    Route::patch('/sublimation/{sublimation}/update-status', [SublimationController::class, 'updateStatus'])->name('sublimations.update-status');
    Route::patch('/sublimation/{sublimation}/update-staff', [SublimationController::class, 'updateStaff'])->name('sublimations.update-staff');
    Route::patch('/sublimation/{sublimation}/update-duedate', [SublimationController::class, 'updateDueDate'])->name('sublimations.update-duedate');
    Route::post('/sublimations/{sublimation}/duplicate', [SublimationController::class, 'duplicate'])->name('sublimations.duplicate');
    Route::post('/sublimations/{sublimation}/tags', [SublimationTagController::class, 'addTag'])->name('sublimations.tags.add');
    Route::delete('/sublimations/{sublimation}/tags/{tag}', [SublimationTagController::class, 'removeTag'])->name('sublimations.tags.remove');
    Route::patch('/sublimations/{sublimation}/tags/{tag}', [SublimationTagController::class, 'updateQuantity'])->name('sublimations.tags.update-quantity');

    Route::get('/sublimations/{sublimation}/images', [SublimationImageController::class, 'index'])->name('sublimations.images.index');
    Route::post('/sublimations/{sublimation}/images', [SublimationImageController::class, 'store'])->name('sublimations.images.store');
    Route::delete('/sublimations/{sublimation}/images/{image}', [SublimationImageController::class, 'destroy'])->name('sublimations.images.destroy');

    Route::resource('sublimations', SublimationController::class);
    Route::resource('customers', CustomerController::class);

    Route::get('sales/print', [SaleController::class, 'print'])->name('sales.print');
    Route::patch('sales/payment/{transaction}', [SaleController::class, 'updatePayment'])->name('sales.update-payment');
    Route::patch('sales/refund/{transaction}', [SaleController::class, 'refundPayment'])->name('sales.refund-payment');
    Route::post('sales/{sale}/attachment', [SaleController::class, 'storeAttachment'])->name('sales.attachment.store');
    Route::delete('sales/{sale}/attachment', [SaleController::class, 'destroyAttachment'])->name('sales.attachment.destroy');
    Route::resource('sales', SaleController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::patch('purchase-orders/{purchaseOrder}/status', [PurchaseOrderController::class, 'updateStatus'])->name('purchase-orders.status.update');
    Route::post('purchase-orders/{purchaseOrder}/transactions', [PurchaseOrderController::class, 'createTransaction'])->name('purchase-orders.transactions.store');
    Route::patch('purchase-orders/{purchaseOrder}/update-staff', [PurchaseOrderController::class, 'updateStaff'])->name('purchase-orders.update-staff');
    Route::resource('purchase-orders', PurchaseOrderController::class);
    Route::resource('endorsements', EndorsementController::class);
    Route::get('/expenses/print', [ExpenseController::class, 'print'])->name('expenses.print');
    Route::patch('/expenses/{expense}/void', [ExpenseController::class, 'void'])->name('expenses.void');
    Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
    Route::post('/expenses/{expense}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject');
    Route::resource('expenses', ExpenseController::class);

    Route::get('incentives', [IncentiveController::class, 'index'])->name('incentives.index');
    Route::post('incentives/pay', [IncentiveController::class, 'pay'])->name('incentives.pay');

    Route::inertia('payroll', 'payroll/dashboard/index')->name('payroll.index');

    Route::prefix('payroll')->name('payroll.')->group(function () {
        Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('employees/create', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
        Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::post('employees/{employee}/deactivate', [EmployeeController::class, 'deactivate'])->name('employees.deactivate');
        Route::post('employees/{employee}/rehire', [EmployeeController::class, 'rehire'])->name('employees.rehire');
        Route::post('employees/{employee}/link-user', [EmployeeController::class, 'linkUser'])->name('employees.link-user');
        Route::post('employees/{employee}/unlink-user', [EmployeeController::class, 'unlinkUser'])->name('employees.unlink-user');
        Route::post('employees/sync-user/{user}', [EmployeeController::class, 'syncUser'])->name('employees.sync-user');
        Route::post('employees/sync-all', [EmployeeController::class, 'syncAll'])->name('employees.sync-all');

        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit.index');

        Route::get('attendance', [TimeLogController::class, 'index'])->name('attendance.index');
        Route::post('attendance/punch', [TimeLogController::class, 'punch'])
            ->middleware('throttle:30,1')
            ->name('attendance.punch');
        Route::post('attendance/manual', [TimeLogController::class, 'manual'])->name('attendance.manual');
        Route::put('employee/profile', [EmployeeController::class, 'updateSelf'])->name('employee.profile.update');

        Route::get('holidays', [HolidayController::class, 'index'])->name('holidays.index');
        Route::post('holidays', [HolidayController::class, 'store'])->name('holidays.store');
        Route::put('holidays/{holiday}', [HolidayController::class, 'update'])->name('holidays.update');
        Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

        Route::post('employees/{employee}/schedules', [EmployeeScheduleController::class, 'store'])->name('employees.schedules.store');
        Route::put('employees/schedules/{schedule}', [EmployeeScheduleController::class, 'update'])->name('employees.schedules.update');
        Route::delete('employees/schedules/{schedule}', [EmployeeScheduleController::class, 'destroy'])->name('employees.schedules.destroy');

        Route::get('attendance-sheets', [AttendanceSheetController::class, 'index'])->name('attendance.sheets.index');
        Route::get('attendance-sheets/{employee}', [AttendanceSheetController::class, 'show'])->name('attendance.sheets.show');
        Route::post('attendance-sheets/{employee}/logs', [AttendanceSheetController::class, 'storeLog'])->name('attendance.logs.store');
        Route::delete('attendance-sheets/{employee}/logs/{log}', [AttendanceSheetController::class, 'destroyLog'])->name('attendance.logs.destroy');
        Route::get('attendance-geo', [AttendanceSheetController::class, 'geo'])->name('attendance.geo');
        Route::post('fines', [FineController::class, 'store'])->name('fines.store');
        Route::delete('fines/{fine}', [FineController::class, 'destroy'])->name('fines.destroy');

        Route::get('periods', [PayrollPeriodController::class, 'index'])->name('periods.index');
        Route::post('periods/generate', [PayrollPeriodController::class, 'generate'])->name('periods.generate');
        Route::get('periods/{period}', [PayrollPeriodController::class, 'show'])->name('periods.show');
        Route::post('periods/{period}/check', [PayrollPeriodController::class, 'check'])->name('periods.check');
        Route::post('periods/{period}/recompute', [PayrollPeriodController::class, 'recompute'])->name('periods.recompute');
        Route::post('periods/{period}/approve', [PayrollPeriodController::class, 'approve'])->name('periods.approve');
        Route::post('periods/{period}/void', [PayrollPeriodController::class, 'void'])->name('periods.void');
        Route::delete('periods/{period}', [PayrollPeriodController::class, 'destroy'])->name('periods.destroy');
        Route::get('periods/{period}/payslip/{item}', [PayrollPeriodController::class, 'payslip'])->name('payslip');
        Route::get('my-payslip', [PayrollPeriodController::class, 'myPayslip'])->name('my-payslip');

        Route::get('work-week', [WorkWeekTableController::class, 'index'])->name('work-week.index');
        Route::get('work-week/print', [WorkWeekTableController::class, 'print'])->name('work-week.print');

        Route::get('reports', [PayrollReportController::class, 'index'])->name('reports.index');
        Route::get('reports/print', [PayrollReportController::class, 'print'])->name('reports.print');
        Route::get('reports/branch-payables', [PayrollReportController::class, 'branchPayables'])->name('reports.branch-payables');

        Route::get('sss-brackets', [SssBracketController::class, 'index'])->name('sss.brackets.index');
        Route::post('sss-brackets', [SssBracketController::class, 'store'])->name('sss.brackets.store');
        Route::put('sss-brackets/{bracket}', [SssBracketController::class, 'update'])->name('sss.brackets.update');
        Route::delete('sss-brackets/{bracket}', [SssBracketController::class, 'destroy'])->name('sss.brackets.destroy');

        Route::get('company-config', [CompanyConfigController::class, 'index'])->name('company.config.index');
        Route::post('company-config', [CompanyConfigController::class, 'update'])->name('company.config.update');

        Route::get('overtime-requests', [OvertimeRequestController::class, 'index'])->name('overtime.index');
        Route::post('overtime-requests', [OvertimeRequestController::class, 'store'])->name('overtime.store');
        Route::post('overtime-requests/{overtimeRequest}/approve', [OvertimeRequestController::class, 'approve'])->name('overtime.approve');
        Route::post('overtime-requests/{overtimeRequest}/deny', [OvertimeRequestController::class, 'deny'])->name('overtime.deny');

        Route::get('leave-requests', [LeaveRequestController::class, 'index'])->name('leaves.index');
        Route::post('leave-requests', [LeaveRequestController::class, 'store'])->name('leaves.store');
        Route::post('leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->name('leaves.approve');
        Route::post('leave-requests/{leaveRequest}/deny', [LeaveRequestController::class, 'deny'])->name('leaves.deny');
        Route::post('leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])->name('leaves.cancel');
        Route::post('leave-requests/reset', [LeaveRequestController::class, 'resetLeave'])->name('leaves.reset');
        Route::delete('leave-requests/{leaveRequest}', [LeaveRequestController::class, 'destroy'])->name('leaves.destroy');

        Route::get('correction-requests', [CorrectionRequestController::class, 'index'])->name('corrections.index');
        Route::post('correction-requests', [CorrectionRequestController::class, 'store'])->name('corrections.store');
        Route::post('correction-requests/{correction}/approve', [CorrectionRequestController::class, 'approve'])->name('corrections.approve');
        Route::post('correction-requests/{correction}/deny', [CorrectionRequestController::class, 'deny'])->name('corrections.deny');

        Route::get('cash-advances', [CashAdvanceController::class, 'index'])->name('cash-advances.index');
        Route::post('cash-advances', [CashAdvanceController::class, 'store'])->name('cash-advances.store');
        Route::post('cash-advances/{cashAdvance}/approve', [CashAdvanceController::class, 'approve'])->name('cash-advances.approve');
        Route::post('cash-advances/{cashAdvance}/deny', [CashAdvanceController::class, 'deny'])->name('cash-advances.deny');

        // Sewed Items
        Route::get('sewed-items', [SewedItemController::class, 'index'])->name('sewed-items.index');
        Route::post('sewed-items/payslip', [SewedItemController::class, 'generatePayslip'])->name('sewed-items.payslip');
        Route::post('sewed-items/payslip/{payslip}/approve', [SewedItemController::class, 'approvePayslip'])->name('sewed-items.payslip.approve');
        Route::post('sewed-items/payslip/{payslip}/cancel', [SewedItemController::class, 'cancelPayslip'])->name('sewed-items.payslip.cancel');
        Route::post('sewed-items', [SewedItemController::class, 'store'])->name('sewed-items.store');
        Route::put('sewed-items/{sewedItem}', [SewedItemController::class, 'update'])->name('sewed-items.update');
        Route::delete('sewed-items/{sewedItem}', [SewedItemController::class, 'destroy'])->name('sewed-items.destroy');

        // Payroll Settings
        Route::get('settings', [PayrollSettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [PayrollSettingController::class, 'update'])->name('settings.update');

        // Admin maintenance commands (superadmin only)
        Route::get('admin/fix-no-break-fine', function () {
            if (! auth()->user()->isSuperAdmin()) {
                abort(403);
            }

            Artisan::call('attendance:fix-no-break-fine');

            return back()->with('success', 'No-break fine fix applied to existing attendance sheets.');
        })->name('admin.fix-no-break-fine');

        Route::get('admin/recalculate-late-deductions', function () {
            if (! auth()->user()->isSuperAdmin()) {
                abort(403);
            }

            Artisan::call('attendance:recalculate-late');

            return back()->with('success', 'Late deductions recalculated for all unlocked attendance sheets.');
        })->name('admin.recalculate-late-deductions');
    });

    Route::prefix('api')->group(function () {
        Route::get('/customers', [CustomerController::class, 'indexApiList'])->name('api.customers.index');
    });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});
