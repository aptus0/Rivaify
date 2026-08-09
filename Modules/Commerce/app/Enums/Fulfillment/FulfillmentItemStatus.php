<?php

namespace Modules\Commerce\Enums\Fulfillment;

enum FulfillmentItemStatus: string
{
    case Pending = 'pending';
    case Picked = 'picked';
    case Packed = 'packed';
    case Cancelled = 'cancelled';
}
