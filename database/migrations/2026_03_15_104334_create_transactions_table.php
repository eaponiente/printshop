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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // Identification
            $table->string('invoice_number')->unique()->comment('Unique reference for the billing document');
            $table->string('guest_name')->comment('Full name of the customer/guest');
            $table->string('particular')->comment('Primary service or item being billed');
            $table->text('description')->nullable()->comment('Detailed breakdown of the transaction');

            // Financials (Decimal prevents rounding errors common with floats)
            $table->decimal('amount_total', 12, 2)->comment('The gross amount to be charged');
            $table->decimal('amount_paid', 12, 2)->default(0)->comment('The total amount successfully collected');
            $table->decimal('balance', 12, 2)->default(0)->comment('Remaining amount due (total minus paid)');

            // Metadata
            $table->string('payment_type')->comment('Method used (e.g., Cash, GCash, Card)');
            $table->string('status')->comment('Current state: pending, partial, paid, or void');

            // Relationships
            $table->foreignId('staff_id')->constrained('users')->onDelete('cascade')->comment('The employee who processed this record');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade')->comment('The physical location where the transaction occurred');

            // Timestamps
            $table->dateTime('transaction_date')->comment('The date the service was rendered or initiated');
            $table->dateTime('fulfilled_at')->nullable()->comment('Timestamp when the balance reached zero');

            // Audit Trail
            $table->text('change_reason')->nullable()->comment('Explanation provided if the record was modified after creation');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
