<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->string('correction_type', 30);
            $table->dateTime('requested_time')->nullable();
            $table->string('reason', 500);
            $table->string('status', 20)->default('pending');
            $table->string('denial_reason', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('resolved_time_log_id')->nullable()->constrained('time_logs')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'date', 'correction_type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correction_requests');
    }
};
