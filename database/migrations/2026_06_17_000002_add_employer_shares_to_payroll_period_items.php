<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_period_items', function (Blueprint $table) {
            $table->decimal('sss_employer', 10, 2)->default(0)->after('sss_deduction');
            $table->decimal('philhealth_employer', 10, 2)->default(0)->after('philhealth_deduction');
            $table->decimal('pagibig_employer', 10, 2)->default(0)->after('pagibig_deduction');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_period_items', function (Blueprint $table) {
            $table->dropColumn(['sss_employer', 'philhealth_employer', 'pagibig_employer']);
        });
    }
};
