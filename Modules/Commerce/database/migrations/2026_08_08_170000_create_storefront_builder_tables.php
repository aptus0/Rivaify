<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('version')->default('1.0.0');
            $table->string('category')->default('fashion');
            $table->json('defaults');
            $table->timestamps();
        });

        Schema::create('store_themes', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('theme_id')->nullable()->constrained('themes')->nullOnDelete();
            $table->string('name');
            $table->string('status')->default('draft');
            $table->json('tokens');
            $table->foreignId('published_version_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });

        Schema::create('builder_documents', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('store_theme_id')->constrained('store_themes')->cascadeOnDelete();
            $table->string('resource_type');
            $table->string('resource_key')->default('home');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->json('document');
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['store_theme_id', 'resource_type', 'resource_key'], 'builder_documents_resource_unique');
            $table->index(['store_id', 'resource_type']);
        });

        Schema::create('theme_versions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('store_theme_id')->constrained('store_themes')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status')->default('saved');
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['store_theme_id', 'version']);
            $table->index(['store_id', 'status']);
        });

        Schema::create('storefront_publications', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('store_theme_id')->constrained('store_themes')->cascadeOnDelete();
            $table->foreignId('theme_version_id')->constrained('theme_versions')->cascadeOnDelete();
            $table->json('manifest');
            $table->timestamp('published_at');
            $table->timestamps();

            $table->index(['store_id', 'published_at']);
        });

        Schema::create('page_templates', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('store_theme_id')->constrained('store_themes')->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->string('handle');
            $table->foreignId('builder_document_id')->nullable()->constrained('builder_documents')->nullOnDelete();
            $table->timestamps();

            $table->unique(['store_theme_id', 'type', 'handle']);
        });

        Schema::create('store_pages', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('title');
            $table->string('handle');
            $table->json('seo')->nullable();
            $table->foreignId('builder_document_id')->nullable()->constrained('builder_documents')->nullOnDelete();
            $table->timestamps();

            $table->unique(['store_id', 'handle']);
        });

        Schema::create('navigation_menus', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->string('handle');
            $table->json('items');
            $table->timestamps();

            $table->unique(['store_id', 'handle']);
        });

        Schema::create('theme_assets', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('store_theme_id')->nullable()->constrained('store_themes')->nullOnDelete();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->string('alt_text')->nullable();
            $table->json('focal_point')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'store_theme_id']);
        });

        Schema::create('preview_tokens', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('theme_version_id')->nullable()->constrained('theme_versions')->cascadeOnDelete();
            $table->string('token')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preview_tokens');
        Schema::dropIfExists('theme_assets');
        Schema::dropIfExists('navigation_menus');
        Schema::dropIfExists('store_pages');
        Schema::dropIfExists('page_templates');
        Schema::dropIfExists('storefront_publications');
        Schema::dropIfExists('theme_versions');
        Schema::dropIfExists('builder_documents');
        Schema::dropIfExists('store_themes');
        Schema::dropIfExists('themes');
    }
};
