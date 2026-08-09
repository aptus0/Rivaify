<?php

namespace Modules\Ecosystem\Enums;

enum IntegrationCategory: string
{
    case SocialCommerce = 'social_commerce';
    case Payment = 'payment';
    case Shipping = 'shipping';
    case Marketplace = 'marketplace';
    case Analytics = 'analytics';
    case Marketing = 'marketing';
    case Accounting = 'accounting';
    case Developer = 'developer';

    public function label(): string
    {
        return match ($this) {
            self::SocialCommerce => 'Sosyal Ticaret',
            self::Payment => 'Ödeme',
            self::Shipping => 'Kargo',
            self::Marketplace => 'Pazaryeri',
            self::Analytics => 'Analitik',
            self::Marketing => 'Pazarlama',
            self::Accounting => 'Muhasebe',
            self::Developer => 'Developer',
        };
    }
}
