<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->time('schedule_start_time')->nullable();
            $table->time('schedule_end_time')->nullable();
            $table->json('rest_days')->nullable();
            $table->decimal('daily_rate', 10, 2)->default(0);
            $table->integer('late_minutes')->default(0);
            $table->decimal('late_deduction', 10, 2)->default(0);
            $table->integer('undertime_minutes')->default(0);
            $table->decimal('undertime_deduction', 10, 2)->default(0);
            $table->integer('overtime_minutes')->default(0);
            $table->decimal('overtime_pay', 10, 2)->default(0);
            $table->decimal('overtime_multiplier', 5, 2)->nullable();
            $table->string('holiday_type', 20)->nullable();
            $table->integer('holiday_pay_percent')->nullable();
            $table->decimal('holiday_pay', 10, 2)->default(0);
            $table->string('leave_type', 20)->nullable();
            $table->string('leave_duration', 20)->nullable();
            $table->boolean('leave_is_paid')->default(false);
            $table->decimal('leave_hours_credited', 5, 2)->default(0);
            $table->decimal('fine_deduction', 10, 2)->default(0);
            $table->decimal('hours_worked', 5, 2)->default(0);
            $table->decimal('daily_wage', 10, 2)->default(0);
            $table->boolean('is_present')->default(false);
            $table->string('absence_type', 30)->nullable();
            $table->boolean('is_rest_day')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index('date');
            $table->index('locked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sheets');
    }
};
