<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Ecosystem\Enums\IntegrationStatus;
use Modules\Ecosystem\Enums\IntegrationWebhookStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_integrations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('integration_key');
            $table->string('status')->default(IntegrationStatus::Pending->value);
            $table->json('configuration')->nullable();
            // Encrypted (Model-level `encrypted:array` cast) — never stored
            // or returned to the frontend as plaintext (brief §7).
            $table->text('credentials')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'integration_key']);
        });

        Schema::create('integration_webhooks', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('integration_key');
            $table->string('external_event_id');
            $table->string('event_type');
            $table->json('payload');
            $table->string('status')->default(IntegrationWebhookStatus::Received->value);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['integration_key', 'external_event_id']);
            $table->index(['status', 'received_at']);
            $table->index(['store_id', 'integration_key']);
        });

        Schema::create('integration_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('integration_key');
            $table->string('type');
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['store_id', 'integration_key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_activity_logs');
        Schema::dropIfExists('integration_webhooks');
        Schema::dropIfExists('store_integrations');
    }
};
