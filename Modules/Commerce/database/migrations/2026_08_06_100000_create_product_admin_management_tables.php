<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'meta_title')) {
                $table->string('meta_title')->nullable()->after('description');
            }
            if (! Schema::hasColumn('products', 'meta_description')) {
                $table->text('meta_description')->nullable()->after('meta_title');
            }
            if (! Schema::hasColumn('products', 'package_width')) {
                $table->decimal('package_width', 10, 2)->nullable()->after('requires_shipping');
            }
            if (! Schema::hasColumn('products', 'package_height')) {
                $table->decimal('package_height', 10, 2)->nullable()->after('package_width');
            }
            if (! Schema::hasColumn('products', 'package_length')) {
                $table->decimal('package_length', 10, 2)->nullable()->after('package_height');
            }
            if (! Schema::hasColumn('products', 'package_dimension_unit')) {
                $table->string('package_dimension_unit', 2)->default('cm')->after('package_length');
            }
        });

        if (! Schema::hasTable('product_media')) {
            Schema::create('product_media', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('media_type', 16)->default('image');
            $table->string('storage_disk', 32);
            $table->string('storage_path');
            $table->string('original_filename');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->index(['store_id', 'product_id', 'position']);
            });
        }

        if (! Schema::hasTable('product_tags')) {
            Schema::create('product_tags', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['product_id', 'name']);
            $table->index(['store_id', 'name']);
            });
        }

        if (! Schema::hasTable('inventory_locations')) {
            Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('type')->default('warehouse');
            $table->string('address_line_1')->nullable();
            $table->string('province')->nullable();
            $table->string('district')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('fulfillment_enabled')->default(true);
            $table->boolean('inventory_enabled')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'code']);
            $table->index(['store_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('inventory_items')) {
            Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->boolean('is_tracked')->default(true);
            $table->boolean('allow_oversell')->default(false);
            $table->timestamps();

            $table->unique(['store_id', 'product_variant_id']);
            });
        }

        if (! Schema::hasTable('inventory_levels')) {
            Schema::create('inventory_levels', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->unsignedInteger('available_quantity')->default(0);
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->unsignedInteger('incoming_quantity')->default(0);
            $table->timestamps();

            $table->unique(['inventory_item_id', 'inventory_location_id']);
            $table->index(['store_id', 'inventory_item_id']);
            });
        }

        if (! Schema::hasTable('inventory_movements')) {
            Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->string('type');
            $table->integer('quantity_delta');
            $table->unsignedInteger('quantity_before');
            $table->unsignedInteger('quantity_after');
            $table->string('reason')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->index(['store_id', 'inventory_item_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_levels');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_locations');
        Schema::dropIfExists('product_tags');
        Schema::dropIfExists('product_media');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'meta_title', 'meta_description', 'package_width', 'package_height', 'package_length',
                'package_dimension_unit',
            ]);
        });
    }
};