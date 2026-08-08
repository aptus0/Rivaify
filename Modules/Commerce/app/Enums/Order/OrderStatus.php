<?php

namespace Modules\Commerce\Enums\Order;

enum OrderStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Archived = 'archived';
}