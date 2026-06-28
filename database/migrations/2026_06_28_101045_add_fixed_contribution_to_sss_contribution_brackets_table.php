<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sss_contribution_brackets', function (Blueprint $table) {
            $table->decimal('employee_contribution', 10, 2)->nullable()->after('employer_percentage');
            $table->decimal('employer_contribution', 10, 2)->nullable()->after('employee_contribution');
        });
    }

    public function down(): void
    {
        Schema::table('sss_contribution_brackets', function (Blueprint $table) {
            $table->dropColumn(['employee_contribution', 'employer_contribution']);
        });
    }
};
