<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sheets', function (Blueprint $table) {
            $table->index(['employee_id', 'date'], 'attendance_sheets_employee_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sheets', function (Blueprint $table) {
            $table->dropIndex('attendance_sheets_employee_date_index');
        });
    }
};
