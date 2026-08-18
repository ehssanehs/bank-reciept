<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('upload_token', 64)->index();
            $table->string('original_name')->nullable();
            $table->string('stored_name');
            $table->string('path');
            $table->string('disk')->default('local');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('sha256', 64);
            $table->string('perceptual_hash')->nullable();
            $table->string('status')->default('stored');
            $table->string('uploaded_via', 20)->default('web');
            $table->unsignedBigInteger('telegram_user_id')->nullable();
            $table->foreignUuid('uploaded_by_user_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('sha256');
            $table->index('perceptual_hash');
            $table->index(['payment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
};
