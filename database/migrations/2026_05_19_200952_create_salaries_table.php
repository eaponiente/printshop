<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete()->comment('Employee reference');
            $table->decimal('daily_rate', 10, 2)->comment('Daily rate at this point in time');
            $table->date('effective_date')->comment('Date this rate became effective');
            $table->date('end_date')->nullable()->comment('Date this rate ended (null = current)');
            $table->text('notes')->nullable()->comment('Reason for rate change');
            $table->timestamps();

            $table->index(['employee_id', 'effective_date']);
            $table->index(['employee_id', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salaries');
    }
};
