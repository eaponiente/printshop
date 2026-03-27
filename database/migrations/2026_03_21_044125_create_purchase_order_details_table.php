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
        Schema::create('purchase_order_details', function (Blueprint $table) {
            $table->id();

            // Link to the Header
            $table->foreignId('purchase_order_id')->constrained()->onDelete('cascade');

            // Item Details
            $table->string('item_name'); // e.g., "Office Paper"
            $table->text('item_description')->nullable();

            // Quantities & Pricing
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_details');
    }
};
