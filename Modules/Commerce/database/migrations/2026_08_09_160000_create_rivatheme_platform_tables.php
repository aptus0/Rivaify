<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_publishers', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('namespace')->unique();
            $table->string('name');
            $table->string('trust_level')->default('custom');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('theme_releases', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('theme_id')->constrained('themes')->cascadeOnDelete();
            $table->foreignId('publisher_id')->nullable()->constrained('theme_publishers')->nullOnDelete();
            $table->string('version');
            $table->string('engine_constraint');
            $table->string('api_version')->default('2026-08');
            $table->string('riva_lang_version')->default('1.0');
            $table->json('manifest');
            $table->foreignId('compiled_artifact_id')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['theme_id', 'version']);
            $table->index(['status', 'published_at']);
        });

        Schema::create('theme_packages', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
            $table->foreignId('theme_id')->nullable()->constrained('themes')->nullOnDelete();
            $table->foreignId('theme_release_id')->nullable()->constrained('theme_releases')->nullOnDelete();
            $table->string('source')->default('custom_upload');
            $table->string('trust_level')->default('custom');
            $table->string('status')->default('quarantined');
            $table->string('original_filename');
            $table->string('quarantine_disk')->default('local');
            $table->string('quarantine_path');
            $table->string('sha256', 64);
            $table->unsignedBigInteger('size_bytes');
            $table->json('manifest')->nullable();
            $table->json('file_index')->nullable();
            $table->json('security_report')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['sha256']);
        });

        Schema::create('theme_compiled_artifacts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
            $table->foreignId('theme_id')->nullable()->constrained('themes')->nullOnDelete();
            $table->foreignId('theme_release_id')->nullable()->constrained('theme_releases')->nullOnDelete();
            $table->foreignId('theme_package_id')->nullable()->constrained('theme_packages')->nullOnDelete();
            $table->string('engine_version')->default('2.0');
            $table->string('artifact_version')->default('1');
            $table->string('checksum', 64);
            $table->json('artifact');
            $table->timestamps();

            $table->index(['store_id', 'theme_id']);
            $table->index(['checksum']);
        });

        Schema::create('theme_compatibility_reports', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
            $table->foreignId('theme_package_id')->nullable()->constrained('theme_packages')->cascadeOnDelete();
            $table->foreignId('theme_release_id')->nullable()->constrained('theme_releases')->cascadeOnDelete();
            $table->string('status')->default('processing');
            $table->json('stages');
            $table->json('errors')->nullable();
            $table->json('warnings')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });

        Schema::table('theme_releases', function (Blueprint $table) {
            $table->foreign('compiled_artifact_id')->references('id')->on('theme_compiled_artifacts')->nullOnDelete();
        });

        Schema::table('store_themes', function (Blueprint $table) {
            $table->foreignId('theme_release_id')->nullable()->after('theme_id')->constrained('theme_releases')->nullOnDelete();
            $table->string('source')->default('official')->after('status');
            $table->string('trust_level')->default('official')->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('store_themes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('theme_release_id');
            $table->dropColumn(['source', 'trust_level']);
        });

        Schema::table('theme_releases', function (Blueprint $table) {
            $table->dropForeign(['compiled_artifact_id']);
        });

        Schema::dropIfExists('theme_compatibility_reports');
        Schema::dropIfExists('theme_compiled_artifacts');
        Schema::dropIfExists('theme_packages');
        Schema::dropIfExists('theme_releases');
        Schema::dropIfExists('theme_publishers');
    }
};
