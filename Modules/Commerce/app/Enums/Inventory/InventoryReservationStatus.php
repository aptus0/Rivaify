<?php

namespace Modules\Commerce\Enums\Inventory;

enum InventoryReservationStatus: string
{
    case Active = 'active';
    case Committed = 'committed';
    case Released = 'released';
    case Expired = 'expired';
}