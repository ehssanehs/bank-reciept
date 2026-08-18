<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_users', function (Blueprint $table) {
            $table->unsignedBigInteger('chat_id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('username')->nullable();
            $table->string('language', 8)->default('en');
            $table->string('state')->nullable();
            $table->json('state_payload')->nullable();
            $table->foreignUuid('linked_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('link_code')->nullable()->index();
            $table->timestamp('link_code_expires_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('username');
        });

        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('chat_id');
            $table->unsignedBigInteger('message_id');
            $table->text('text')->nullable();
            $table->string('media_type', 20)->nullable();
            $table->string('media_file_id')->nullable();
            $table->string('media_path')->nullable();
            $table->string('caption')->nullable();
            $table->string('command')->nullable();
            $table->json('payload')->nullable();
            $table->string('status')->default('received');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['chat_id', 'message_id'], 'tg_msg_chat_msg_unique');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
        Schema::dropIfExists('telegram_users');
    }
};
