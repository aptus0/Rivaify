<?php

namespace Modules\Commerce\Events\Inventory;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Commerce\Models\Inventory\InventoryLevel;

class InventoryAdjusted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly InventoryLevel $level,
        public readonly int $quantityBefore,
        public readonly int $quantityAfter,
        public readonly string $reason,
    ) {}
}
