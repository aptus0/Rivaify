<?php

namespace Modules\Commerce\Enums\Fulfillment;

enum FulfillmentStatus: string
{
    case Unfulfilled = 'unfulfilled';
    case Processing = 'processing';
    case Picking = 'picking';
    case Packing = 'packing';
    case ReadyToShip = 'ready_to_ship';
    case Shipped = 'shipped';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
    case ReturnToSender = 'return_to_sender';
    case Returned = 'returned';
}
