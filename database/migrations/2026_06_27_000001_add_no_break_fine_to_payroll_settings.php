<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payroll_settings')->insertOrIgnore([
            'key' => 'no_break_fine',
            'value' => '20',
            'type' => 'numeric',
            'description' => 'Peso fine deducted when an employee has no lunch-break punches.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('payroll_settings')->where('key', 'no_break_fine')->delete();
    }
};
