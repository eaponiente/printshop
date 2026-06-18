<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value')->nullable();
            $table->string('type')->default('numeric')->comment('numeric, string, boolean');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        DB::table('payroll_settings')->insert([
            [
                'key' => 'late_deduction_per_minute',
                'value' => '5',
                'type' => 'numeric',
                'description' => 'Flat peso amount charged for each minute of lateness within the threshold.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'late_deduction_threshold_minutes',
                'value' => '20',
                'type' => 'numeric',
                'description' => 'Minutes after which the penalty switches from per-minute flat rate to hourly-rate-based.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_settings');
    }
};
