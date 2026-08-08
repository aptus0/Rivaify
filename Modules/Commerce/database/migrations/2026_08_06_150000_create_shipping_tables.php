<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Commerce\Enums\Shipping\ShippingMethodStatus;
use Modules\Commerce\Enums\Shipping\ShippingMethodType;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['store_id', 'name']);
        });

        Schema::create('shipping_zone_regions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('shipping_zone_id')->constrained('shipping_zones')->cascadeOnDelete();
            $table->char('country_code', 2);
            $table->string('province')->nullable();
            $table->timestamps();

            $table->unique(['shipping_zone_id', 'country_code', 'province']);
        });

        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('shipping_zone_id')->nullable()->constrained('shipping_zones')->nullOnDelete();
            $table->string('name');
            $table->string('type')->default(ShippingMethodType::FlatRate->value);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('minimum_order', 12, 2)->nullable();
            $table->decimal('maximum_order', 12, 2)->nullable();
            $table->unsignedSmallInteger('estimated_days_min')->nullable();
            $table->unsignedSmallInteger('estimated_days_max')->nullable();
            $table->string('status')->default(ShippingMethodStatus::Active->value);
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'shipping_zone_id']);
        });

        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->foreign('shipping_method_id')->references('id')->on('shipping_methods')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->dropForeign(['shipping_method_id']);
        });

        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('shipping_zone_regions');
        Schema::dropIfExists('shipping_zones');
    }
};