<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sublimations', function (Blueprint $table) {
            $table->id();
            $table->integer('branch_id');
            $table->integer('customer_id');
            $table->integer('user_id');
            $table->enum('status', ['pending', 'active', 'finished', 'released'])->default('pending');
            $table->string('particular');
            $table->string('description');
            $table->date('due_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sublimations');
    }
};
