<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('User who performed the action');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete()->comment('Branch context (falls back to model branch for superadmin)');
            $table->string('action', 50)->comment('created, updated, deleted, rehired');
            $table->string('model_type', 255)->comment('FQN of the affected model');
            $table->unsignedBigInteger('model_id')->comment('Primary key of the affected record');
            $table->json('before')->nullable()->comment('Old values of changed fields only');
            $table->json('after')->nullable()->comment('New values of changed fields only');
            $table->string('ip_address', 45)->comment('Request IP address');
            $table->string('user_agent', 500)->nullable()->comment('Browser user agent');
            $table->timestamp('created_at')->useCurrent()->comment('When the action occurred');

            $table->index(['model_type', 'model_id']);
            $table->index('action');
            $table->index('user_id');
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
