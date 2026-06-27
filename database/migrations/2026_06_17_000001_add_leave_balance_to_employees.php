<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('default_paid_leave_days', 5, 1)->default(5)->after('current_daily_rate');
            $table->decimal('paid_leave_balance', 5, 1)->default(5)->after('default_paid_leave_days');
        });

        DB::table('employees')->where('status', 'active')->update([
            'default_paid_leave_days' => 5,
            'paid_leave_balance' => 5,
        ]);
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['default_paid_leave_days', 'paid_leave_balance']);
        });
    }
};
