<?php

namespace Modules\Commerce\Services\Shipping;

use Illuminate\Support\Collection;
use Modules\Commerce\Enums\Shipping\ShippingMethodStatus;
use Modules\Commerce\Enums\Shipping\ShippingMethodType;
use Modules\Commerce\Exceptions\Shipping\ShippingMethodNotAvailableException;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Customer\CustomerAddress;
use Modules\Commerce\Models\Shipping\ShippingMethod;
use Modules\Commerce\ValueObjects\Money;
use Modules\Commerce\ValueObjects\ShippingQuote;

class ShippingEngine
{
    /**
     * @return Collection<int, ShippingQuote>
     */
    public function quotes(Cart $cart, CustomerAddress $address): Collection
    {
        $this->assertStoreOwnership($cart, $address);

        return ShippingMethod::query()
            ->with('zone.regions')
            ->where('status', ShippingMethodStatus::Active->value)
            ->get()
            ->filter(fn (ShippingMethod $method): bool => $this->isAvailable($method, $cart, $address))
            ->map(fn (ShippingMethod $method): ShippingQuote => $this->quote($cart, $address, $method))
            ->values();
    }

    public function quote(Cart $cart, CustomerAddress $address, ShippingMethod $method): ShippingQuote
    {
        $this->assertStoreOwnership($cart, $address);

        if ($method->store_id !== $cart->store_id || ! $this->isAvailable($method->loadMissing('zone.regions'), $cart, $address)) {
            throw new ShippingMethodNotAvailableException('Shipping method is not available for this checkout.');
        }

        $amount = $method->type === ShippingMethodType::FreeShipping
            ? Money::zero($cart->currency)
            : Money::fromDecimal($method->price, $cart->currency);

        return new ShippingQuote($method, $amount);
    }

    private function isAvailable(ShippingMethod $method, Cart $cart, CustomerAddress $address): bool
    {
        if ($method->status !== ShippingMethodStatus::Active || ! $this->matchesZone($method, $address)) {
            return false;
        }

        $orderAmount = Money::fromDecimal($cart->subtotal, $cart->currency)
            ->subtract(Money::fromDecimal($cart->discount_total, $cart->currency))
            ->add(Money::fromDecimal($cart->tax_total, $cart->currency));

        if ($method->minimum_order !== null && $orderAmount->isLessThan(Money::fromDecimal($method->minimum_order, $cart->currency))) {
            return false;
        }

        return $method->maximum_order === null
            || ! $orderAmount->isGreaterThan(Money::fromDecimal($method->maximum_order, $cart->currency));
    }

    private function matchesZone(ShippingMethod $method, CustomerAddress $address): bool
    {
        if ($method->zone === null || $method->zone->regions->isEmpty()) {
            return true;
        }

        return $method->zone->regions->contains(function ($region) use ($address): bool {
            if (strtoupper($region->country_code) !== strtoupper($address->country_code)) {
                return false;
            }

            return $region->province === null
                || mb_strtolower($region->province) === mb_strtolower((string) $address->province);
        });
    }

    private function assertStoreOwnership(Cart $cart, CustomerAddress $address): void
    {
        if ($cart->store_id !== $address->store_id) {
            throw new ShippingMethodNotAvailableException('Cart and shipping address belong to different stores.');
        }
    }
}