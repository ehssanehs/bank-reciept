<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event', 50);
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignUuid('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('event');
            $table->index('created_at');
            $table->index('payment_id');
            $table->index('device_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
