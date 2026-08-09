<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $table->string('status')->default('unfulfilled');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('picked_at')->nullable();
            $table->timestamp('packed_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('package')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status', 'created_at']);
            $table->index(['store_id', 'order_id']);
        });

        Schema::create('fulfillment_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('fulfillment_id')->constrained('fulfillments')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('picked_quantity')->default(0);
            $table->string('status')->default('pending');
            $table->timestamp('picked_at')->nullable();
            $table->timestamps();

            $table->unique(['fulfillment_id', 'order_item_id']);
            $table->index(['store_id', 'status']);
        });

        if (! Schema::hasTable('shipments')) {
            Schema::create('shipments', function (Blueprint $table) {
                $table->id();
                $table->ulid('ulid')->unique();
                $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('fulfillment_id')->nullable()->constrained('fulfillments')->nullOnDelete();
                $table->string('provider');
                $table->string('external_reference');
                $table->string('provider_shipment_id')->nullable();
                $table->string('tracking_number')->nullable();
                $table->string('tracking_url')->nullable();
                $table->string('status')->default('pending');
                $table->string('service_code')->nullable();
                $table->decimal('package_weight', 10, 3)->nullable();
                $table->json('package_dimensions')->nullable();
                $table->string('label_disk')->nullable();
                $table->string('label_path')->nullable();
                $table->timestamp('shipped_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();

                $table->unique(['store_id', 'external_reference']);
                $table->index(['store_id', 'status', 'created_at']);
                $table->index(['store_id', 'order_id']);
                $table->index(['provider', 'provider_shipment_id']);
            });
        } else {
            Schema::table('shipments', function (Blueprint $table) {
                if (! Schema::hasColumn('shipments', 'ulid')) {
                    $table->ulid('ulid')->nullable()->unique()->after('id');
                }
                if (! Schema::hasColumn('shipments', 'fulfillment_id')) {
                    $table->foreignId('fulfillment_id')->nullable()->after('order_id')->constrained('fulfillments')->nullOnDelete();
                }
                if (! Schema::hasColumn('shipments', 'external_reference')) {
                    $table->string('external_reference')->nullable()->after('provider');
                }
                if (! Schema::hasColumn('shipments', 'provider_shipment_id')) {
                    $table->string('provider_shipment_id')->nullable()->after('external_reference');
                }
                if (! Schema::hasColumn('shipments', 'tracking_url')) {
                    $table->string('tracking_url')->nullable()->after('tracking_number');
                }
                if (! Schema::hasColumn('shipments', 'service_code')) {
                    $table->string('service_code')->nullable()->after('status');
                }
                if (! Schema::hasColumn('shipments', 'package_weight')) {
                    $table->decimal('package_weight', 10, 3)->nullable()->after('service_code');
                }
                if (! Schema::hasColumn('shipments', 'package_dimensions')) {
                    $table->json('package_dimensions')->nullable()->after('package_weight');
                }
                if (! Schema::hasColumn('shipments', 'label_disk')) {
                    $table->string('label_disk')->nullable()->after('package_dimensions');
                }
                if (! Schema::hasColumn('shipments', 'label_path')) {
                    $table->string('label_path')->nullable()->after('label_disk');
                }
                if (! Schema::hasColumn('shipments', 'shipped_at')) {
                    $table->timestamp('shipped_at')->nullable()->after('label_path');
                }
                if (! Schema::hasColumn('shipments', 'delivered_at')) {
                    $table->timestamp('delivered_at')->nullable()->after('shipped_at');
                }
            });
        }

        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('provider_event_id')->nullable();
            $table->string('provider_status')->nullable();
            $table->string('normalized_status');
            $table->string('location')->nullable();
            $table->string('message');
            $table->timestamp('occurred_at');
            $table->string('payload_reference')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['shipment_id', 'provider_event_id']);
            $table->index(['store_id', 'normalized_status', 'occurred_at']);
        });

        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('return_number');
            $table->string('status')->default('requested');
            $table->string('reason')->nullable();
            $table->text('customer_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->string('return_tracking_number')->nullable();
            $table->string('return_tracking_url')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'return_number']);
            $table->index(['store_id', 'status', 'requested_at']);
            $table->index(['store_id', 'order_id']);
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('reason_code')->default('other');
            $table->string('condition')->nullable();
            $table->string('resolution')->default('refund');
            $table->boolean('restock')->default(false);
            $table->timestamps();

            $table->unique(['return_id', 'order_item_id']);
            $table->index(['store_id', 'resolution']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('return_id')->nullable()->constrained('returns')->nullOnDelete();
            $table->string('provider');
            $table->string('idempotency_key');
            $table->string('provider_refund_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('status')->default('pending');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'idempotency_key']);
            $table->index(['store_id', 'status', 'requested_at']);
            $table->index(['store_id', 'order_id']);
        });

        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('type');
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('platform_fee', 12, 2)->default(0);
            $table->decimal('provider_fee', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->char('currency', 3);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('status')->default('posted');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['store_id', 'type', 'reference_type', 'reference_id']);
            $table->index(['store_id', 'currency', 'occurred_at']);
            $table->index(['store_id', 'status']);
        });

        Schema::create('merchant_settlements', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_settlement_id')->nullable();
            $table->decimal('gross', 12, 2)->default(0);
            $table->decimal('fees', 12, 2)->default(0);
            $table->decimal('refunds', 12, 2)->default(0);
            $table->decimal('net', 12, 2)->default(0);
            $table->decimal('expected_net', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->char('currency', 3)->default('TRY');
            $table->string('status')->default('pending');
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamp('expected_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'provider', 'provider_settlement_id']);
            $table->index(['store_id', 'status', 'period_end']);
        });

        Schema::create('payment_disputes', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('provider');
            $table->string('external_dispute_id');
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('TRY');
            $table->string('reason')->nullable();
            $table->string('status')->default('opened');
            $table->timestamp('due_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_dispute_id']);
            $table->index(['store_id', 'status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_disputes');
        Schema::dropIfExists('merchant_settlements');
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('fulfillment_items');
        Schema::dropIfExists('fulfillments');
    }
};
