<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_sms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignUuid('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->string('local_id')->nullable();
            $table->string('sender');
            $table->longText('message_body');
            $table->timestamp('received_at')->nullable();
            $table->string('sms_hash', 64);
            $table->string('status')->default('received');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'sms_hash'], 'bank_sms_device_hash_unique');
            $table->index('sender');
            $table->index('received_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_sms');
    }
};
