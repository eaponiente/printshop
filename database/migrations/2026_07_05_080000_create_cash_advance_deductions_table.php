<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-cash-advance ledger of how much was deducted by a given payroll
     * period item. An item's ca_deduction can span multiple cash advances
     * (FIFO), so this is what lets a period deletion reverse the exact
     * amounts taken from each advance instead of just the lump sum.
     */
    public function up(): void
    {
        Schema::create('cash_advance_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_advance_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->index('cash_advance_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_advance_deductions');
    }
};
