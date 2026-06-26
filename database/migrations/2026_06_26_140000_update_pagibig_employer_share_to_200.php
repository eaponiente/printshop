<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('company_configurations')
            ->where('key', 'pagibig_monthly_employer_share')
            ->where('value', '100')
            ->update(['value' => '200', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('company_configurations')
            ->where('key', 'pagibig_monthly_employer_share')
            ->where('value', '200')
            ->update(['value' => '100', 'updated_at' => now()]);
    }
};
