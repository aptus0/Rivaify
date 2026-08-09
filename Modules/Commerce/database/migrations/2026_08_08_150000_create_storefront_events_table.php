<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_events', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('event_type', 32);
            $table->char('session_hash', 64);
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('checkout_id')->nullable()->constrained('checkout_sessions')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('page_path', 255)->nullable();
            $table->string('source', 100)->default('direct');
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('referrer_host', 253)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['store_id', 'event_type', 'occurred_at'], 'sfe_store_type_time_index');
            $table->index(['store_id', 'session_hash', 'occurred_at'], 'sfe_store_session_time_index');
            $table->index(['store_id', 'source', 'occurred_at'], 'sfe_store_source_time_index');
            $table->unique(['store_id', 'event_type', 'order_id'], 'sfe_purchase_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_events');
    }
};
