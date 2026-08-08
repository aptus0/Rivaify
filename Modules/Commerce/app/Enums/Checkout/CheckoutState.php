<?php

namespace Modules\Commerce\Enums\Checkout;

enum CheckoutState: string
{
    case Initiated = 'initiated';
    case CustomerInformation = 'customer_information';
    case Address = 'address';
    case Shipping = 'shipping';
    case Payment = 'payment';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';
}