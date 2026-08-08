<?php

namespace Modules\Commerce\Enums\Inventory;

enum InventoryLocationStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}