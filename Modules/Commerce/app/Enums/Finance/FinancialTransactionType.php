<?php

namespace Modules\Commerce\Enums\Finance;

enum FinancialTransactionType: string
{
    case Sale = 'sale';
    case Refund = 'refund';
    case PartialRefund = 'partial_refund';
    case Chargeback = 'chargeback';
    case Adjustment = 'adjustment';
    case PlatformFee = 'platform_fee';
    case ProviderFee = 'provider_fee';
    case Payout = 'payout';
}
