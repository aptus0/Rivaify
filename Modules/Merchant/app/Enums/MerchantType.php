<?php

namespace Modules\Merchant\Enums;

enum MerchantType: string
{
    case Individual = 'individual';
    case SoleProprietorship = 'sole_proprietorship';
    case LimitedCompany = 'limited_company';
    case JointStockCompany = 'joint_stock_company';
    case Other = 'other';
}
