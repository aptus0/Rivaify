<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_profiles', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();

            $table->string('tax_office')->nullable();
            $table->string('tax_number');
            $table->string('legal_entity_name');
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            $table->unique('merchant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_profiles');
    }
};
