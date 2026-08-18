<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocr_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignUuid('receipt_id')->constrained('payment_receipts')->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('status')->default('processing');
            $table->longText('raw_text')->nullable();
            $table->json('extracted_fields')->nullable();
            $table->json('normalized_fields')->nullable();
            $table->float('confidence')->default(0);
            $table->unsignedInteger('attempt')->default(1);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ocr_results');
    }
};
