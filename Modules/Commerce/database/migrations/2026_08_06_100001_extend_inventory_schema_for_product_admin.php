<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_locations', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_locations', 'type')) {
                $table->string('type')->default('warehouse')->after('code');
            }
            if (! Schema::hasColumn('inventory_locations', 'address_line_1')) {
                $table->string('address_line_1')->nullable()->after('type');
            }
            if (! Schema::hasColumn('inventory_locations', 'province')) {
                $table->string('province')->nullable()->after('address_line_1');
            }
            if (! Schema::hasColumn('inventory_locations', 'district')) {
                $table->string('district')->nullable()->after('province');
            }
            if (! Schema::hasColumn('inventory_locations', 'phone')) {
                $table->string('phone')->nullable()->after('district');
            }
            if (! Schema::hasColumn('inventory_locations', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('phone');
            }
            if (! Schema::hasColumn('inventory_locations', 'fulfillment_enabled')) {
                $table->boolean('fulfillment_enabled')->default(true)->after('is_active');
            }
            if (! Schema::hasColumn('inventory_locations', 'inventory_enabled')) {
                $table->boolean('inventory_enabled')->default(true)->after('fulfillment_enabled');
            }
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_items', 'allow_oversell')) {
                $table->boolean('allow_oversell')->default(false)->after('is_tracked');
            }
        });

        Schema::table('inventory_levels', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_levels', 'incoming_quantity')) {
                $table->unsignedInteger('incoming_quantity')->default(0)->after('reserved_quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_levels', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_levels', 'incoming_quantity')) {
                $table->dropColumn('incoming_quantity');
            }
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_items', 'allow_oversell')) {
                $table->dropColumn('allow_oversell');
            }
        });

        Schema::table('inventory_locations', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('inventory_locations', 'type') ? 'type' : null,
                Schema::hasColumn('inventory_locations', 'address_line_1') ? 'address_line_1' : null,
                Schema::hasColumn('inventory_locations', 'province') ? 'province' : null,
                Schema::hasColumn('inventory_locations', 'district') ? 'district' : null,
                Schema::hasColumn('inventory_locations', 'phone') ? 'phone' : null,
                Schema::hasColumn('inventory_locations', 'is_active') ? 'is_active' : null,
                Schema::hasColumn('inventory_locations', 'fulfillment_enabled') ? 'fulfillment_enabled' : null,
                Schema::hasColumn('inventory_locations', 'inventory_enabled') ? 'inventory_enabled' : null,
            ]);
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};