<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS shipments_store_external_reference_unique ON shipments (store_id, external_reference) WHERE external_reference IS NOT NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS shipments_store_status_created_idx ON shipments (store_id, status, created_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS shipments_provider_shipment_idx ON shipments (provider, provider_shipment_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS shipments_provider_shipment_idx');
        DB::statement('DROP INDEX IF EXISTS shipments_store_status_created_idx');
        DB::statement('DROP INDEX IF EXISTS shipments_store_external_reference_unique');
    }
};
