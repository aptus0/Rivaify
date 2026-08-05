<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_addresses', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('business_profile_id')->constrained('business_profiles')->cascadeOnDelete();

            // registered | billing | shipping — kept as a plain string
            // rather than an enum column since new address types are a
            // low-risk, likely addition and shouldn't need a migration.
            $table->string('type')->default('registered');

            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country_code', 2)->default('TR');

            $table->timestamps();

            $table->index(['business_profile_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_addresses');
    }
};
