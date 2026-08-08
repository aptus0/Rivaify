<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Commerce\Enums\Discount\DiscountStatus;
use Modules\Commerce\Enums\Discount\DiscountType;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('type')->default(DiscountType::Percentage->value);
            $table->decimal('value', 12, 2)->default(0);
            $table->string('status')->default(DiscountStatus::Active->value);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->decimal('minimum_purchase', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'code']);
            $table->index(['store_id', 'status']);
        });

        Schema::create('discount_conditions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('discount_id')->constrained('discounts')->cascadeOnDelete();
            $table->string('type');
            $table->string('operator')->nullable();
            $table->json('value');
            $table->timestamps();

            $table->index(['discount_id', 'type']);
        });

        Schema::create('discount_usages', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('discount_id')->constrained('discounts')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('checkout_id')->nullable()->constrained('checkout_sessions')->nullOnDelete();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['discount_id', 'checkout_id']);
            $table->index(['store_id', 'discount_id']);
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->foreignId('discount_id')->nullable()->after('customer_id')->constrained('discounts')->nullOnDelete();
            $table->string('discount_code')->nullable()->after('discount_id');
        });

        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->foreignId('discount_id')->nullable()->after('customer_id')->constrained('discounts')->nullOnDelete();
            $table->string('discount_code')->nullable()->after('discount_id');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_id');
            $table->dropColumn('discount_code');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_id');
            $table->dropColumn('discount_code');
        });

        Schema::dropIfExists('discount_usages');
        Schema::dropIfExists('discount_conditions');
        Schema::dropIfExists('discounts');
    }
};