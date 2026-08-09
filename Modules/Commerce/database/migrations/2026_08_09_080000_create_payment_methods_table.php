<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Commerce\Enums\Payment\PaymentMethodStatus;

/**
 * Sprint 6 (checkout & payments core) — saved cards. Only ever the
 * provider's own tokens (PayTR's utoken/ctoken-style pair) plus display
 * metadata; never a real PAN, CVV, or expiry that could reconstruct one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_customer_token');
            $table->string('provider_card_token');
            $table->string('brand')->nullable();
            $table->string('last4', 4)->nullable();
            $table->unsignedTinyInteger('expiry_month')->nullable();
            $table->unsignedSmallInteger('expiry_year')->nullable();
            $table->string('status')->default(PaymentMethodStatus::Active->value);
            $table->boolean('is_default')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_card_token']);
            $table->index(['store_id', 'customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
