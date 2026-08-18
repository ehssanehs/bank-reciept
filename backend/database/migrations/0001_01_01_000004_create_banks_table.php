<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('currency')->default('IRR');
            $table->json('sender_patterns');
            $table->string('parser_class')->nullable();
            $table->string('amount_pattern')->nullable();
            $table->string('tracking_pattern')->nullable();
            $table->string('date_pattern')->nullable();
            $table->string('time_pattern')->nullable();
            $table->json('transaction_type_rules')->nullable();
            $table->string('timezone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
