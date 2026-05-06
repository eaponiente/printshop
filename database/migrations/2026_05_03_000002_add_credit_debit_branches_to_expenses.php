<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('creditor_branch_id')->nullable()->after('branch_id')->constrained('branches')->nullOnDelete();
            $table->foreignId('debtor_branch_id')->nullable()->after('creditor_branch_id')->constrained('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('debtor_branch_id');
            $table->dropConstrainedForeignId('creditor_branch_id');
        });
    }
};
