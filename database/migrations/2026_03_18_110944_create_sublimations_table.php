<?php

use App\Enums\Sublimations\SublimationStatus;
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
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount_total', 12, 2)->comment('The gross amount to be charged');
            $table->enum('status', SublimationStatus::cases())->default(SublimationStatus::FOR_APPROVAL);
            $table->string('description');
            $table->text('notes')->nullable();
            $table->date('due_at');
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transaction_type')->default('retail');
            $table->boolean('production_authorized')->default(false);
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
