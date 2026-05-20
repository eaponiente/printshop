<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->comment('Project name');
            $table->text('description')->nullable()->comment('Project description');
            $table->date('start_date')->comment('Project start date');
            $table->date('end_date')->nullable()->comment('Project end date');
            $table->decimal('budget', 12, 2)->nullable()->comment('Total project budget');
            $table->string('status', 20)->default('active')->comment('active, completed, cancelled');
            $table->timestamps();
        });

        Schema::create('employee_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('assigned_at')->comment('Date assigned to project');
            $table->date('ended_at')->nullable()->comment('Date removed from project');
            $table->timestamps();

            $table->unique(['employee_id', 'project_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_project');
        Schema::dropIfExists('projects');
    }
};
