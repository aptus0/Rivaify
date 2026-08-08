<?php

namespace Modules\Commerce\Http\Presenters;

use Modules\Commerce\Models\Customer\Customer;
use Modules\Commerce\Models\Customer\CustomerAddress;
use Modules\Commerce\Models\Customer\CustomerEvent;
use Modules\Commerce\ValueObjects\Money;

class CustomerPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Customer $customer): array
    {
        return [
            'id' => $customer->ulid,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'name' => trim("{$customer->first_name} {$customer->last_name}"),
            'email' => $customer->email,
            'phone' => $customer->phone,
            'status' => $customer->status->value,
            'total_orders' => $customer->total_orders,
            'total_spent' => $customer->total_spent,
            'last_order_at' => $customer->last_order_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Customer $customer): array
    {
        $customer->loadMissing(['addresses', 'orders', 'events']);
        $currency = $customer->store?->default_currency ?? 'TRY';

        return [
            ...self::summary($customer),
            'accepts_marketing' => $customer->accepts_marketing,
            'average_order_value' => $customer->total_orders === 0
                ? '0.00'
                : Money::fromMinor(
                    intdiv(
                        Money::fromDecimal($customer->total_spent, $currency)->amount + intdiv($customer->total_orders, 2),
                        $customer->total_orders,
                    ),
                    $currency,
                )->toDecimal(),
            'addresses' => $customer->addresses->map(fn (CustomerAddress $address): array => [
                'id' => $address->ulid,
                'type' => $address->type->value,
                'first_name' => $address->first_name,
                'last_name' => $address->last_name,
                'country_code' => $address->country_code,
                'province' => $address->province,
                'district' => $address->district,
                'address_line_1' => $address->address_line_1,
                'address_line_2' => $address->address_line_2,
                'postal_code' => $address->postal_code,
                'is_default' => $address->is_default,
            ])->values()->all(),
            'orders' => $customer->orders->sortByDesc('placed_at')->map(fn ($order): array => OrderPresenter::summary($order))->values()->all(),
            'timeline' => $customer->events->map(fn (CustomerEvent $event): array => [
                'type' => $event->type,
                'created_at' => $event->created_at?->toIso8601String(),
                'metadata' => $event->metadata,
            ])->values()->all(),
        ];
    }
}