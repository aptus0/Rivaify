<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_profiles', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();

            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            $table->unique('merchant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_profiles');
    }
};
