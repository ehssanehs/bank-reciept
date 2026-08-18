<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignUuid('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->unsignedBigInteger('chat_id')->nullable();
            $table->string('type', 50);
            $table->string('channel', 20);
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->string('status')->default('created');
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['payment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
