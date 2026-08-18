<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('telegram_user_id')->nullable();
            $table->string('code')->unique();
            $table->string('order_id')->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 8)->default('IRR');
            $table->string('tracking_number')->nullable();
            $table->string('status', 30)->default('CREATED');
            $table->unsignedInteger('risk_score')->default(0);
            $table->float('ocr_confidence')->default(0);
            $table->string('recommendation')->nullable();
            $table->text('verification_notes')->nullable();
            $table->foreignUuid('matched_transaction_id')->nullable();
            $table->string('upload_token', 64)->index();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('amount');
            $table->index('tracking_number');
            $table->index('created_at');
            $table->index(['customer_id', 'status']);
            $table->index(['telegram_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
