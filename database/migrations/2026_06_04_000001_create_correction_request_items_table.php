<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correction_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correction_request_id')->constrained('correction_requests')->cascadeOnDelete();
            $table->string('punch_type', 20);
            $table->dateTime('requested_time');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correction_request_items');
    }
};
