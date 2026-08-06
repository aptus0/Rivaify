<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Commerce\Enums\Catalog\CategoryStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();

            // Nullable self-reference set after table creation (below) since
            // the FK target is this same table.
            $table->foreignId('parent_id')->nullable();

            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();

            $table->unsignedSmallInteger('position')->default(0);
            $table->string('status')->default(CategoryStatus::Draft->value);

            $table->timestamps();

            $table->unique(['store_id', 'slug']);
            $table->index(['store_id', 'parent_id']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
