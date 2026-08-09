<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id(); $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name'); $table->string('channel')->default('online_store');
            $table->string('objective')->default('sales'); $table->string('status')->default('draft');
            $table->decimal('budget', 12, 2)->nullable(); $table->char('currency', 3)->default('TRY');
            $table->timestamp('starts_at')->nullable(); $table->timestamp('ends_at')->nullable();
            $table->json('content')->nullable(); $table->timestamps();
            $table->index(['store_id', 'status']); $table->index(['store_id', 'channel']);
        });
    }
    public function down(): void { Schema::dropIfExists('marketing_campaigns'); }
};
