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
        Schema::table('sewed_item_tag', function (Blueprint $table) {
            $table->decimal('price_per_piece', 10, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sewed_item_tag', function (Blueprint $table) {
            $table->dropColumn('price_per_piece');
        });
    }
};
