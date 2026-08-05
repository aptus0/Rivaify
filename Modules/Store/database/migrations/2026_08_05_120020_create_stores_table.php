<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Store\Enums\OnboardingStatus;
use Modules\Store\Enums\StoreStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();

            $table->string('name');
            $table->string('slug')->unique();

            $table->string('status')->default(StoreStatus::Draft->value);
            $table->string('onboarding_status')->default(OnboardingStatus::AccountCreated->value);

            $table->string('default_currency', 3)->default('TRY');
            $table->string('default_locale', 10)->default('tr');
            $table->string('timezone')->default('Europe/Istanbul');
            $table->string('country_code', 2)->default('TR');

            $table->timestamps();

            $table->index('status');
            $table->index('onboarding_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
