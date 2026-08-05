<?php

namespace Modules\Store\Enums;

enum StoreStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
}
