<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Commerce\Services\Fulfillment\StoreFulfillmentProvisioner;
use Modules\Store\Models\Store;

return new class extends Migration
{
    public function up(): void
    {
        $provisioner = app(StoreFulfillmentProvisioner::class);

        Store::query()
            ->orderBy('id')
            ->eachById(fn (Store $store) => $provisioner->provision($store));
    }

    public function down(): void
    {
        // Defaults may already be in use or edited by merchants; destructive rollback is unsafe.
    }
};
