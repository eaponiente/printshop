<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE expenses MODIFY COLUMN status ENUM('paid', 'void', 'pending', 'rejected') DEFAULT 'paid'");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE expenses MODIFY COLUMN status ENUM('paid', 'void') DEFAULT 'paid'");
    }
};
