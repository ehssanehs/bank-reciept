<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_matches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignUuid('bank_transaction_id')->nullable()->constrained('bank_transactions')->nullOnDelete();
            $table->foreignUuid('receipt_id')->nullable()->constrained('payment_receipts')->nullOnDelete();
            $table->string('match_type', 30);
            $table->float('score')->default(0);
            $table->json('matched_fields')->nullable();
            $table->string('result', 30)->default('candidate');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('match_type');
            $table->index(['payment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_matches');
    }
};
