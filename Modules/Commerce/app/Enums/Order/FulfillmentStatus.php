<?php

namespace Modules\Commerce\Enums\Order;

enum FulfillmentStatus: string
{
    case Unfulfilled = 'unfulfilled';
    case Partial = 'partial';
    case Fulfilled = 'fulfilled';
    case Returned = 'returned';
}