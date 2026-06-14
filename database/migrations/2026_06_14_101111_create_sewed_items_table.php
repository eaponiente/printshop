<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sewed_items')) {
            return;
        }

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
    }
};
