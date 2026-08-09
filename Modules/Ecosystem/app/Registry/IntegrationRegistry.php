<?php

namespace Modules\Ecosystem\Registry;

use Modules\Ecosystem\Connectors\Social\MetaConnector;
use Modules\Ecosystem\Enums\IntegrationAvailability;
use Modules\Ecosystem\Enums\IntegrationCategory;

/**
 * The single source of truth for "which integrations exist and can they
 * actually be connected right now". Nothing in the frontend hardcodes a
 * provider list or a status label — it all comes from here, and
 * availability is computed from real config presence, not a fixed flag
 * (brief §3: "Buradaki statüler gerçek configuration'dan gelmeli. UI
 * içinde hardcode etmeyelim.").
 */
class IntegrationRegistry
{
    /**
     * @return array<string, IntegrationDefinition>
     */
    public static function all(): array
    {
        $definitions = [
            new IntegrationDefinition(
                key: 'facebook',
                name: 'Facebook',
                category: IntegrationCategory::SocialCommerce,
                description: 'Ürün kataloğunuzu Facebook Shop\'a bağlayın ve senkronize edin.',
                logo: 'facebook.svg',
                availability: self::metaConfigured() ? IntegrationAvailability::Available : IntegrationAvailability::ComingSoon,
                capabilities: ['catalog_sync', 'product_sync', 'inventory_sync'],
                authenticationType: 'oauth2',
                connectorClass: self::metaConfigured() ? MetaConnector::class : null,
                documentationUrl: 'https://developers.facebook.com/docs/commerce-platform',
            ),
            new IntegrationDefinition(
                key: 'instagram',
                name: 'Instagram',
                category: IntegrationCategory::SocialCommerce,
                description: 'Ürün ve katalog senkronizasyonu ile Instagram\'da satış yapın.',
                logo: 'instagram.svg',
                availability: self::metaConfigured() ? IntegrationAvailability::Available : IntegrationAvailability::ComingSoon,
                capabilities: ['catalog_sync', 'product_sync', 'inventory_sync'],
                authenticationType: 'oauth2',
                connectorClass: self::metaConfigured() ? MetaConnector::class : null,
                documentationUrl: 'https://developers.facebook.com/docs/commerce-platform',
            ),
            new IntegrationDefinition(
                key: 'tiktok',
                name: 'TikTok',
                category: IntegrationCategory::SocialCommerce,
                description: 'TikTok Shop üzerinden ürünlerinizi satışa sunun.',
                logo: 'tiktok.svg',
                availability: IntegrationAvailability::ComingSoon,
                capabilities: [],
                authenticationType: 'oauth2',
                connectorClass: null,
            ),
        ];

        return collect($definitions)->keyBy('key')->all();
    }

    public static function find(string $key): ?IntegrationDefinition
    {
        return self::all()[$key] ?? null;
    }

    /**
     * @return array<string, IntegrationDefinition[]>
     */
    public static function byCategory(): array
    {
        return collect(self::all())
            ->groupBy(fn (IntegrationDefinition $definition): string => $definition->category->value)
            ->map(fn ($group) => $group->values()->all())
            ->all();
    }

    private static function metaConfigured(): bool
    {
        return filled(config('ecosystem.connectors.meta.app_id')) && filled(config('ecosystem.connectors.meta.app_secret'));
    }
}
