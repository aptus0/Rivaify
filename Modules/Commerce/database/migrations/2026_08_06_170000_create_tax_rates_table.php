<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Commerce\Enums\Tax\TaxRateStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->char('country_code', 2);
            $table->decimal('rate', 5, 2);
            $table->boolean('is_inclusive')->default(false);
            $table->string('status')->default(TaxRateStatus::Active->value);
            $table->timestamps();

            $table->index(['store_id', 'country_code', 'status']);
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->boolean('tax_inclusive')->default(false)->after('tax_total');
        });

        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->boolean('tax_inclusive')->default(false)->after('tax_total');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->dropColumn('tax_inclusive');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('tax_inclusive');
        });

        Schema::dropIfExists('tax_rates');
    }
};