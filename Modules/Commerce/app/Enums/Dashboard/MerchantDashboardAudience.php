<?php

namespace Modules\Commerce\Enums\Dashboard;

use Modules\Store\Enums\StoreUserRole;

enum MerchantDashboardAudience: string
{
    case Full = 'full';
    case Staff = 'staff';
    case Support = 'support';
    case Developer = 'developer';

    public static function fromRole(StoreUserRole $role): self
    {
        return match ($role) {
            StoreUserRole::Owner, StoreUserRole::Admin, StoreUserRole::Manager => self::Full,
            StoreUserRole::Staff => self::Staff,
            StoreUserRole::Support => self::Support,
            StoreUserRole::Developer => self::Developer,
        };
    }

    public function canViewSales(): bool
    {
        return $this === self::Full;
    }

    public function canViewOrders(): bool
    {
        return in_array($this, [self::Full, self::Staff, self::Support], true);
    }

    public function canViewInventory(): bool
    {
        return in_array($this, [self::Full, self::Staff], true);
    }

    public function canViewCustomers(): bool
    {
        return in_array($this, [self::Full, self::Support], true);
    }

    public function canViewStoreOperations(): bool
    {
        return in_array($this, [self::Full, self::Developer], true);
    }

    /** @return array<string, bool> */
    public function visibility(): array
    {
        return [
            'net_sales' => $this->canViewSales(),
            'orders' => $this->canViewOrders(),
            'average_order' => $this->canViewSales(),
            'customers' => $this->canViewCustomers(),
            'sales_chart' => $this->canViewSales(),
            'action_center' => $this->canViewOrders() || $this->canViewInventory(),
            'inventory' => $this->canViewInventory(),
            'recent_orders' => $this->canViewOrders(),
            'order_status' => $this->canViewOrders(),
            'customer_summary' => $this->canViewCustomers(),
            'top_products' => $this->canViewSales(),
            'store_status' => $this->canViewStoreOperations(),
            'sales_channels' => $this->canViewStoreOperations(),
            'period_filter' => $this !== self::Developer,
        ];
    }
}
