<?php

use App\Enums\Expenses\ExpenseStatus;
use App\Enums\Shared\TypeOfPaymentEnum;
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
        $typeOfPayments = TypeOfPaymentEnum::cases();

        Schema::create('expenses', function (Blueprint $table) use ($typeOfPayments) {
            $table->id();
            // Core Data
            $table->string('description');
            $table->string('vendor_name')->nullable();

            // Financials: 19 total digits, 4 after the decimal point
            $table->decimal('amount', 12);
            $table->enum('status', ExpenseStatus::cases())->default(ExpenseStatus::PAID);

            // Categorization
            // Note: Using your specific types from the TS error earlier
            $table->enum('payment_type', $typeOfPayments)->nullable(); // e.g., 'Credit Card', 'Cash'

            // Business Context
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->string('receipt')->nullable(); // Store the file path to the S3 bucket/local storage

            // Status Tracking
            $table->date('expense_date')->index(); // Indexing dates makes reporting much faster
            $table->text('void_reason')->nullable(); // Indexing dates makes reporting much faster

            $table->timestamps();
            $table->softDeletes(); // Optional: allows "deleting" without losing financial records
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
