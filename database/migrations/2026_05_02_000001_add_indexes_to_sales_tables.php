<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('amount');
            $table->index('payment_type');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index('branch_id');
            $table->index('amount_paid');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['amount']);
            $table->dropIndex(['payment_type']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['branch_id']);
            $table->dropIndex(['amount_paid']);
            $table->dropIndex(['status']);
        });
    }
};
