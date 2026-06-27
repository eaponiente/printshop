<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Payroll\Employee\Enums\EmployeePosition;
use Payroll\Employee\Enums\EmployeeStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number', 50)->unique()->comment('Internal employee identifier');
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete()->comment('Branch the employee belongs to');
            $table->string('first_name', 100)->comment('Employee first name');
            $table->string('last_name', 100)->comment('Employee last name');
            $table->string('middle_name', 100)->nullable()->comment('Employee middle name');
            $table->string('email', 255)->nullable()->unique()->comment('Employee email address');
            $table->string('phone', 20)->nullable()->comment('Contact phone number');
            $table->string('address', 500)->nullable()->comment('Residential address');
            $table->date('birth_date')->nullable()->comment('Date of birth');
            $table->date('hire_date')->comment('Date the employee was hired');
            $table->date('end_date')->nullable()->comment('Date of resignation or termination');
            $table->string('position', 50)->default(EmployeePosition::REGULAR->value)->comment('REGULAR, CONTRACTUAL, or PROJECT_BASED');
            $table->string('status', 20)->default(EmployeeStatus::ACTIVE->value)->comment('ACTIVE, RESIGNED, or TERMINATED');
            $table->decimal('current_daily_rate', 10, 2)->comment('Current daily rate, synced from latest salary record');
            $table->string('sss_number', 20)->nullable()->comment('SSS number');
            $table->string('philhealth_number', 20)->nullable()->comment('PhilHealth number');
            $table->string('pagibig_number', 20)->nullable()->comment('Pag-IBIG MID number');
            $table->string('tin_number', 20)->nullable()->comment('Tax Identification Number');
            $table->text('notes')->nullable()->comment('Internal notes');
            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
