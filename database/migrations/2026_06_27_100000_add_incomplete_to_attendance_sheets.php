<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sheets', function (Blueprint $table) {
            $table->boolean('is_incomplete')->default(false)->after('is_rest_day');
            $table->string('incomplete_reason', 100)->nullable()->after('is_incomplete');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sheets', function (Blueprint $table) {
            $table->dropColumn(['is_incomplete', 'incomplete_reason']);
        });
    }
};
