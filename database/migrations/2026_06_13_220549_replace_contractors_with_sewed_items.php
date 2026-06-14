<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('payroll_period_contractor_items');
        Schema::dropIfExists('contractor_cash_advances');
        Schema::dropIfExists('contractor_projects');
        Schema::dropIfExists('contractors');

        Schema::create('sewed_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sublimation_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->date('sewed_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sewed_items');

        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contractor_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('contract_amount', 12, 2);
            $table->integer('total_installments')->default(1);
            $table->integer('remaining_installments')->default(1);
            $table->decimal('installment_amount', 12, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contractor_cash_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->decimal('remaining_balance', 10, 2);
            $table->string('reason', 500);
            $table->string('status', 20)->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

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
};
