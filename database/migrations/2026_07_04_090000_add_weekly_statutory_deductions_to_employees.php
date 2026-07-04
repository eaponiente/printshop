<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('sss_deduction_per_week', 10, 2)->default(0)->after('pagibig_number')->comment('Fixed SSS employee deduction per payroll period');
            $table->decimal('philhealth_deduction_per_week', 10, 2)->default(0)->after('sss_deduction_per_week')->comment('Fixed PhilHealth employee deduction per payroll period');
            $table->decimal('pagibig_deduction_per_week', 10, 2)->default(0)->after('philhealth_deduction_per_week')->comment('Fixed Pag-IBIG employee deduction per payroll period');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'sss_deduction_per_week',
                'philhealth_deduction_per_week',
                'pagibig_deduction_per_week',
            ]);
        });
    }
};
