<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->enum('payment_type', [
                'cash',
                'card',
                'check',
                'bank_transfer',
                'gcash',
                'credit'
            ])->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->enum('payment_type', [
                'cash',
                'card',
                'check',
                'bank_transfer',
                'gcash',
            ])->nullable()->change();
        });
    }
};
