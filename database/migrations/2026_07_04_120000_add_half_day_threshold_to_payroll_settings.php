<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payroll_settings')->insertOrIgnore([
            'key' => 'half_day_threshold_minutes',
            'value' => '60',
            'type' => 'numeric',
            'description' => 'Minutes late (from schedule start) at which the day becomes an afternoon-only half day: morning unpaid, no late deduction, afternoon session paid.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('payroll_settings')->where('key', 'half_day_threshold_minutes')->delete();
    }
};
