<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')->nullable()->after('tax_inclusive')->constrained('tax_rates')->nullOnDelete();
        });

        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')->nullable()->after('tax_inclusive')->constrained('tax_rates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_rate_id');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_rate_id');
        });
    }
};