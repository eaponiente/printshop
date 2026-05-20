<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benefits', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('Benefit name (e.g., SSS, PhilHealth, Pag-IBIG, HMO)');
            $table->string('type', 50)->default('statutory')->comment('statutory or perk');
            $table->text('description')->nullable()->comment('Benefit description');
            $table->decimal('employer_contribution_percent', 5, 2)->nullable()->comment('Employer contribution percentage');
            $table->decimal('employee_contribution_percent', 5, 2)->nullable()->comment('Employee contribution percentage');
            $table->decimal('employer_contribution_cap', 12, 2)->nullable()->comment('Maximum employer contribution amount');
            $table->decimal('employee_contribution_cap', 12, 2)->nullable()->comment('Maximum employee contribution amount');
            $table->boolean('is_active')->default(true)->comment('Whether this benefit is active');
            $table->timestamps();
        });

        Schema::create('benefit_employee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('benefit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('member_number', 50)->nullable()->comment('Employee member number for this benefit');
            $table->date('effective_date')->comment('Date benefit enrollment started');
            $table->date('end_date')->nullable()->comment('Date enrollment ended (null = active)');
            $table->decimal('custom_employer_cap', 12, 2)->nullable()->comment('Override employer contribution cap');
            $table->decimal('custom_employee_cap', 12, 2)->nullable()->comment('Override employee contribution cap');
            $table->boolean('is_active')->default(true)->comment('Whether this enrollment is active');
            $table->timestamps();

            $table->unique(['benefit_id', 'employee_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benefit_employee');
        Schema::dropIfExists('benefits');
    }
};
