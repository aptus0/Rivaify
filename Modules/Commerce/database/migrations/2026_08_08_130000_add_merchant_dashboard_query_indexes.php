<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(
                ['store_id', 'payment_status', 'currency', 'placed_at'],
                'orders_dashboard_paid_period_idx',
            );
            $table->index(
                ['store_id', 'fulfillment_status', 'placed_at'],
                'orders_dashboard_fulfillment_idx',
            );
            $table->index(
                ['store_id', 'customer_id', 'payment_status', 'placed_at'],
                'orders_dashboard_customer_period_idx',
            );
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['store_id', 'status', 'failed_at'], 'payments_dashboard_failed_idx');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index(['store_id', 'created_at'], 'customers_dashboard_created_idx');
        });

        Schema::table('inventory_levels', function (Blueprint $table) {
            $table->index(
                ['store_id', 'available_quantity'],
                'inventory_levels_dashboard_available_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('inventory_levels', function (Blueprint $table) {
            $table->dropIndex('inventory_levels_dashboard_available_idx');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_dashboard_created_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_dashboard_failed_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_dashboard_customer_period_idx');
            $table->dropIndex('orders_dashboard_fulfillment_idx');
            $table->dropIndex('orders_dashboard_paid_period_idx');
        });
    }
};
