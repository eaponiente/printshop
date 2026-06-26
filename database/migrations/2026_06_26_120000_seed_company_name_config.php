<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('company_configurations')->updateOrInsert(
            ['key' => 'company_name'],
            [
                'value' => 'Printing Shop Management',
                'label' => 'Company Name',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('company_configurations')->where('key', 'company_name')->delete();
    }
};
