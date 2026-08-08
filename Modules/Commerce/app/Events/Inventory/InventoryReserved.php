<?php

namespace Modules\Commerce\Events\Inventory;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Commerce\Models\Inventory\InventoryReservation;

class InventoryReserved
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly InventoryReservation $reservation) {}
}