<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('benefits', function (Blueprint $table) {
            $table->decimal('monthly_amount', 10, 2)->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->string('payslip_label', 100)->nullable();
        });

        Schema::table('benefit_employee', function (Blueprint $table) {
            $table->decimal('custom_monthly_amount', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('benefits', function (Blueprint $table) {
            $table->dropColumn(['monthly_amount', 'is_taxable', 'payslip_label']);
        });

        Schema::table('benefit_employee', function (Blueprint $table) {
            $table->dropColumn('custom_monthly_amount');
        });
    }
};
