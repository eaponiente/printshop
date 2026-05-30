<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('source', 20);
            $table->dateTime('timestamp');
            $table->text('note')->nullable();
            $table->foreignId('duplicate_of')->nullable()->constrained('time_logs')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['employee_id', 'type']);
            $table->index(['employee_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_logs');
    }
};
