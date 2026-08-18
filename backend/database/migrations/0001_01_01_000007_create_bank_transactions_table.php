<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bank_sms_id')->nullable()->constrained('bank_sms')->nullOnDelete();
            $table->foreignUuid('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->foreignUuid('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('transaction_type')->nullable()->index();
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('currency', 8)->default('IRR');
            $table->string('tracking_number')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('card_number')->nullable();
            $table->string('account_number')->nullable();
            $table->string('original_date')->nullable();
            $table->string('original_time')->nullable();
            $table->date('transaction_date')->nullable();
            $table->string('transaction_time')->nullable();
            $table->timestamp('normalized_timestamp')->nullable();
            $table->string('timezone')->nullable();
            $table->longText('raw_message')->nullable();
            $table->json('parsed')->nullable();
            $table->string('hash', 64)->nullable();
            $table->string('status')->default('AVAILABLE');
            $table->foreignUuid('used_by_payment_id')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamps();

            // Core indexes required by the matching engine
            $table->index('tracking_number');
            $table->index('amount');
            $table->index('transaction_date');
            $table->index('normalized_timestamp');
            $table->index('bank_id');
            $table->index('device_id');
            $table->index('status');
            $table->unique('hash', 'bank_tx_hash_unique');

            // One bank transaction → one verified payment (NULL-safe single-use)
            $table->foreign('used_by_payment_id')->references('id')->on('payments')->nullOnDelete();
            $table->unique('used_by_payment_id', 'bank_tx_used_by_payment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
