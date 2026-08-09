<?php

namespace Modules\Commerce\Enums\Analytics;

enum StorefrontEventType: string
{
    case PageView = 'page_view';
    case ProductView = 'product_view';
    case AddToCart = 'add_to_cart';
    case CheckoutStarted = 'checkout_started';
    case Purchase = 'purchase';

    /** @return list<string> */
    public static function clientValues(): array
    {
        return [
            self::PageView->value,
            self::ProductView->value,
            self::AddToCart->value,
            self::CheckoutStarted->value,
        ];
    }
}
