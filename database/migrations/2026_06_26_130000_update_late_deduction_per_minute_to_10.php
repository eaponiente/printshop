<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payroll_settings')
            ->where('key', 'late_deduction_per_minute')
            ->where('value', '5')
            ->update(['value' => '10', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('payroll_settings')
            ->where('key', 'late_deduction_per_minute')
            ->where('value', '10')
            ->update(['value' => '5', 'updated_at' => now()]);
    }
};
