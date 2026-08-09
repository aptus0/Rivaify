<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Commerce\Enums\Payment\PaymentAccountPayoutStatus;
use Modules\Commerce\Enums\Payment\PaymentAccountVerificationStatus;
use Modules\Commerce\Enums\Payment\StorePaymentAccountStatus;

/**
 * Sprint 6 (checkout & payments core) — links a store to its submerchant
 * identity under Rivaify's PayTR Marketplace account. Deliberately holds
 * no provider secrets/API keys in plaintext — only the identifiers and
 * status PayTR itself hands back; actual credentials belong in a secrets
 * store, not this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();

            $table->string('provider');
            $table->string('provider_account_id')->nullable();
            $table->string('provider_submerchant_id')->nullable();

            $table->string('status')->default(StorePaymentAccountStatus::PendingVerification->value);
            $table->string('verification_status')->default(PaymentAccountVerificationStatus::NotStarted->value);
            $table->string('payout_status')->default(PaymentAccountPayoutStatus::Ineligible->value);

            $table->json('capabilities')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'provider']);
            $table->index(['provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_payment_accounts');
    }
};
