<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sheets', function (Blueprint $table) {
            $table->decimal('incentive', 10, 2)->default(0)->after('daily_wage');
        });

        Schema::table('payroll_period_items', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_period_items', 'holiday_pay')) {
                $table->decimal('incentive', 10, 2)->default(0)->after('holiday_pay');
            } else {
                $table->decimal('incentive', 10, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sheets', function (Blueprint $table) {
            $table->dropColumn('incentive');
        });

        Schema::table('payroll_period_items', function (Blueprint $table) {
            $table->dropColumn('incentive');
        });
    }
};
