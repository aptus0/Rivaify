<?php

namespace Modules\Commerce\Services\Order;

use App\Core\Tenancy\CurrentStore;
use Modules\Commerce\Models\Order\OrderSequence;
use Modules\Store\Models\Store;

class OrderNumberGenerator
{
    public function __construct(private readonly CurrentStore $currentStore) {}

    public function next(): string
    {
        $store = Store::query()->lockForUpdate()->findOrFail($this->currentStore->id());
        $sequence = OrderSequence::query()
            ->where('store_id', $store->id)
            ->lockForUpdate()
            ->first();
        if ($sequence === null) {
            $sequence = OrderSequence::query()->create(['next_number' => 1001]);
        }

        $number = $sequence->next_number;
        $sequence->update(['next_number' => $number + 1]);

        return "RV-{$number}";
    }
}