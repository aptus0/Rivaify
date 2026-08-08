<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Commerce\Enums\Payment\PaymentStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('checkout_id')->constrained('checkout_sessions')->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_payment_id')->nullable();
            $table->string('status')->default(PaymentStatus::Pending->value);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('payment_method_type')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_payment_id']);
            $table->index(['store_id', 'checkout_id']);
            $table->index(['store_id', 'order_id']);
            $table->index(['store_id', 'status']);
        });

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

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('provider');
            $table->string('external_event_id');
            $table->string('type');
            $table->json('payload');
            $table->string('status')->default('received');
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_event_id']);
            $table->index(['status', 'received_at']);
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('key');
            $table->string('operation');
            $table->string('request_hash')->nullable();
            $table->string('status')->default('processing');
            $table->json('response')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['store_id', 'key', 'operation']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payments');
    }
};