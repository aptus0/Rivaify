<?php

namespace Modules\Commerce\Enums\Returns;

enum ReturnStatus: string
{
    case Requested = 'requested';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case ReturnShipping = 'return_shipping';
    case InTransit = 'in_transit';
    case Received = 'received';
    case Inspection = 'inspection';
    case RefundPending = 'refund_pending';
    case Refunded = 'refunded';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case PartialRefund = 'partial_refund';
    case StoreCredit = 'store_credit';
    case Exchange = 'exchange';
}
