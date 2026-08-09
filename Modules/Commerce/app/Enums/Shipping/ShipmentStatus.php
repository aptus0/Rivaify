<?php

namespace Modules\Commerce\Enums\Shipping;

enum ShipmentStatus: string
{
    case Pending = 'pending';
    case Created = 'created';
    case LabelCreated = 'label_created';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Returned = 'returned';
}
