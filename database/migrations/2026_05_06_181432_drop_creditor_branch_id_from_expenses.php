<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('creditor_branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('creditor_branch_id')->nullable()->after('branch_id')->constrained('branches')->nullOnDelete();
        });
    }
};
