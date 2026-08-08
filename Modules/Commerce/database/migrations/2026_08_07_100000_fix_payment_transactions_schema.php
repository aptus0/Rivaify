<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The live payment_transactions table was created (in an earlier session,
 * against this same shared database) with a different, simpler schema
 * (order_id/gateway/transaction_id/currency/gateway_response) than what
 * the current PaymentManager/PaymentTransaction code expects
 * (payment_id/type/provider_transaction_id/metadata, no currency column
 * since amount is always in the parent Payment's currency). Confirmed
 * empty (0 rows) before writing this — safe to drop and recreate rather
 * than a column-by-column ALTER.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('payment_transactions');

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('type');
            $table->string('status');
            $table->decimal('amount', 12, 2);
            $table->string('provider_transaction_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['store_id', 'payment_id']);
            $table->unique(['payment_id', 'type', 'provider_transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
