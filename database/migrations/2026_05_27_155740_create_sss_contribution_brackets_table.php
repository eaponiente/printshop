<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sss_contribution_brackets', function (Blueprint $table) {
            $table->id();
            $table->decimal('salary_min', 12, 2);
            $table->decimal('salary_max', 12, 2)->nullable();
            $table->decimal('employee_percentage', 5, 2)->default(5);
            $table->decimal('employer_percentage', 5, 2)->default(10);
            $table->date('effective_from');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sss_contribution_brackets');
    }
};
