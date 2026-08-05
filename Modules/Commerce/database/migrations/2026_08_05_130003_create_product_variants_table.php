<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Commerce\Enums\Catalog\ProductStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            $table->string('title')->default('Default');

            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();

            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();

            $table->decimal('weight', 10, 3)->nullable();
            $table->string('weight_unit', 3)->default('kg');

            $table->boolean('requires_shipping')->default(true);
            $table->boolean('is_taxable')->default(true);

            $table->unsignedSmallInteger('position')->default(0);
            $table->string('status')->default(ProductStatus::Draft->value);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'sku']);
            $table->unique(['store_id', 'barcode']);
            $table->index(['product_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
