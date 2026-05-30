<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_period_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->integer('total_regular_days')->default(0);
            $table->integer('absent_days')->default(0);
            $table->integer('total_late_minutes')->default(0);
            $table->decimal('late_deduction', 10, 2)->default(0);
            $table->integer('total_undertime_minutes')->default(0);
            $table->decimal('undertime_deduction', 10, 2)->default(0);
            $table->integer('total_overtime_minutes')->default(0);
            $table->decimal('overtime_pay', 10, 2)->default(0);
            $table->integer('holiday_pay_days')->default(0);
            $table->decimal('holiday_pay', 10, 2)->default(0);
            $table->integer('leave_paid_days')->default(0);
            $table->decimal('fine_deduction', 10, 2)->default(0);
            $table->decimal('gross_pay', 10, 2)->default(0);
            $table->decimal('deminimis_earnings', 10, 2)->default(0);
            $table->decimal('sss_deduction', 10, 2)->default(0);
            $table->decimal('philhealth_deduction', 10, 2)->default(0);
            $table->decimal('pagibig_deduction', 10, 2)->default(0);
            $table->decimal('ca_deduction', 10, 2)->default(0);
            $table->decimal('net_pay', 10, 2)->default(0);
            $table->decimal('daily_rate', 10, 2)->default(0);
            $table->integer('sss_bracket')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id']);
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_period_items');
    }
};
