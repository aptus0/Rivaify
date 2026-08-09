<?php

namespace App\Core\Tenancy;

use Modules\Store\Enums\StoreUserRole;

final class StoreRolePermissions
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'products.view',
        'products.manage',
        'orders.view',
        'orders.manage',
        'orders.refund',
        'inventory.view',
        'inventory.manage',
        'customers.view',
        'customers.manage',
        'discounts.view',
        'discounts.manage',
        'analytics.view',
        'marketing.view',
        'marketing.manage',
        'settings.view',
        'settings.manage',
        'integrations.view',
        'themes.view',
        'themes.install',
        'themes.edit',
        'themes.publish',
        'themes.delete',
        'pages.view',
        'pages.create',
        'pages.update',
        'pages.delete',
        'navigation.manage',
        'checkout.customize',
    ];

    public static function allows(StoreUserRole $role, string $permission): bool
    {
        $full = in_array($role, [
            StoreUserRole::Owner,
            StoreUserRole::Admin,
            StoreUserRole::Manager,
        ], true);

        return match ($permission) {
            'products.view', 'orders.view', 'discounts.view', 'settings.view', 'integrations.view',
            'themes.view', 'pages.view' => true,
            'inventory.view' => $full || $role === StoreUserRole::Staff,
            'customers.view', 'customers.manage' => $full || $role === StoreUserRole::Support,
            'analytics.view', 'marketing.view', 'marketing.manage', 'orders.refund' => $full,
            'products.manage', 'discounts.manage' => $full,
            'inventory.manage', 'orders.manage' => $full || $role === StoreUserRole::Staff,
            'settings.manage' => in_array($role, [StoreUserRole::Owner, StoreUserRole::Admin], true),
            'themes.edit', 'themes.install', 'pages.create', 'pages.update', 'navigation.manage',
            'checkout.customize' => $full || $role === StoreUserRole::Developer,
            'themes.publish', 'themes.delete', 'pages.delete' => in_array($role, [StoreUserRole::Owner, StoreUserRole::Admin], true),
            default => false,
        };
    }

    /** @return list<string> */
    public static function forRole(StoreUserRole $role): array
    {
        return array_values(array_filter(
            self::PERMISSIONS,
            fn (string $permission): bool => self::allows($role, $permission),
        ));
    }

    /** @return array<string, bool> */
    public static function capabilities(StoreUserRole $role): array
    {
        return [
            'create_product' => self::allows($role, 'products.manage'),
            'create_order' => self::allows($role, 'orders.manage'),
            'create_customer' => self::allows($role, 'customers.manage'),
            'create_discount' => self::allows($role, 'discounts.manage'),
            'adjust_inventory' => self::allows($role, 'inventory.manage'),
            'manage_store' => self::allows($role, 'settings.manage'),
            'view_analytics' => self::allows($role, 'analytics.view'),
            'edit_theme' => self::allows($role, 'themes.edit'),
            'publish_theme' => self::allows($role, 'themes.publish'),
        ];
    }
}
