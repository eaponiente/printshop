<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_period_contractor_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('contractor_projects')->cascadeOnDelete();
            $table->decimal('contract_amount', 12, 2);
            $table->decimal('ca_deduction', 10, 2)->default(0);
            $table->decimal('net_pay', 12, 2);
            $table->timestamps();

            $table->unique(['payroll_period_id', 'project_id'], 'contractor_item_period_project_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_period_contractor_items');
    }
};
