<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Distinguishes Rivaify's own internal reviewers from
            // merchants — checked by EnsureIsRivaifyAdmin middleware on
            // every admin.rivaify.com route. Deliberately a plain column,
            // not a role string: there is exactly one bit of information
            // needed here, and a full roles/permissions system for internal
            // staff is out of scope for Sprint 01.
            $table->boolean('is_rivaify_admin')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_rivaify_admin');
        });
    }
};
