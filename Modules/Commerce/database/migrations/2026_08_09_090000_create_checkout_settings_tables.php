<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Commerce\Enums\Checkout\CheckoutFieldRequirement;

/**
 * Sprint 6 (checkout & payments core) — per-store checkout branding
 * (checkout.rivaify.com renders the shared Rivaify checkout shell around
 * this). `checkout_settings` is the single mutable draft row a merchant
 * edits; `checkout_setting_versions` holds immutable published snapshots,
 * mirroring the store_themes/theme_versions draft-vs-published pattern
 * already used for the storefront builder — same shape, much smaller
 * scope (no page templates, navigation, or asset library here).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_settings', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();

            $table->foreignId('logo_media_id')->nullable()->constrained('theme_assets')->nullOnDelete();
            $table->string('layout')->default('modern');

            $table->string('primary_color')->default('#111111');
            $table->string('background_color')->default('#FFFFFF');
            $table->string('text_color')->default('#111111');

            $table->string('heading_font')->default('Manrope');
            $table->string('body_font')->default('Inter');

            $table->unsignedTinyInteger('button_radius')->default(8);
            $table->unsignedTinyInteger('input_radius')->default(8);

            $table->string('phone_requirement')->default(CheckoutFieldRequirement::Required->value);
            $table->string('company_requirement')->default(CheckoutFieldRequirement::Hidden->value);
            $table->string('tax_number_requirement')->default(CheckoutFieldRequirement::Hidden->value);
            $table->boolean('order_note_enabled')->default(false);
            $table->boolean('marketing_consent_enabled')->default(true);

            $table->json('policy_links')->nullable();

            $table->unsignedInteger('current_version')->default(0);
            $table->timestamps();

            $table->unique('store_id');
        });

        Schema::create('checkout_setting_versions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('checkout_setting_id')->constrained('checkout_settings')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['checkout_setting_id', 'version']);
            $table->index(['store_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_setting_versions');
        Schema::dropIfExists('checkout_settings');
    }
};
