<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Commerce\Enums\Inventory\InventoryReservationStatus;

/**
 * Despite the filename (kept for migration-order compatibility with a
 * branch this was integrated from), this does NOT create
 * inventory_locations/items/levels — those already exist from Sprint 2's
 * 2026_08_06_100000_create_product_admin_management_tables.php with a
 * richer schema (address fields, is_active/fulfillment_enabled/
 * inventory_enabled booleans) than the branch's own version. Only the
 * reservation concept was genuinely new, so that's all this adds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->foreignId('checkout_id')->constrained('checkout_sessions')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status')->default(InventoryReservationStatus::Active->value);
            $table->timestamp('expires_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();

            $table->unique(['checkout_id', 'inventory_item_id']);
            $table->index(['status', 'expires_at']);
            $table->index(['store_id', 'checkout_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_reservations');
    }
};
