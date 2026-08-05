<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Enums\Catalog\ProductType;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();

            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();

            $table->string('product_type')->default(ProductType::Physical->value);
            $table->string('status')->default(ProductStatus::Draft->value);

            $table->string('vendor')->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('requires_shipping')->default(true);

            $table->timestamp('published_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'slug']);
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
