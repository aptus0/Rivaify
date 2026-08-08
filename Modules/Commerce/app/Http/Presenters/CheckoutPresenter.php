<?php

namespace Modules\Commerce\Http\Presenters;

use Modules\Commerce\Models\Checkout\CheckoutSession;

class CheckoutPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(CheckoutSession $checkout): array
    {
        $checkout->loadMissing(['cart.items.product', 'cart.items.variant', 'customer', 'shippingAddress', 'billingAddress', 'shippingMethod', 'discount', 'order']);

        return [
            'id' => $checkout->ulid,
            'token' => $checkout->token,
            'status' => $checkout->status->value,
            'email' => $checkout->email,
            'phone' => $checkout->phone,
            'customer' => $checkout->customer === null ? null : [
                'id' => $checkout->customer->ulid,
                'first_name' => $checkout->customer->first_name,
                'last_name' => $checkout->customer->last_name,
            ],
            'shipping_address' => self::address($checkout->shippingAddress),
            'billing_address' => self::address($checkout->billingAddress),
            'shipping_method' => $checkout->shippingMethod === null ? null : [
                'id' => $checkout->shippingMethod->ulid,
                'name' => $checkout->shippingMethod->name,
                'estimated_days_min' => $checkout->shippingMethod->estimated_days_min,
                'estimated_days_max' => $checkout->shippingMethod->estimated_days_max,
            ],
            'cart' => CartPresenter::present($checkout->cart),
            'currency' => $checkout->currency,
            'subtotal' => $checkout->subtotal,
            'discount_total' => $checkout->discount_total,
            'tax_total' => $checkout->tax_total,
            'tax_inclusive' => $checkout->tax_inclusive,
            'shipping_total' => $checkout->shipping_total,
            'grand_total' => $checkout->grand_total,
            'discount' => $checkout->discount === null ? null : [
                'code' => $checkout->discount_code,
                'type' => $checkout->discount->type->value,
            ],
            'order' => $checkout->order === null ? null : [
                'id' => $checkout->order->ulid,
                'number' => $checkout->order->order_number,
                'payment_status' => $checkout->order->payment_status->value,
                'fulfillment_status' => $checkout->order->fulfillment_status->value,
            ],
            'expires_at' => $checkout->expires_at?->toIso8601String(),
            'completed_at' => $checkout->completed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function address($address): ?array
    {
        if ($address === null) {
            return null;
        }

        return [
            'id' => $address->ulid,
            'first_name' => $address->first_name,
            'last_name' => $address->last_name,
            'company' => $address->company,
            'phone' => $address->phone,
            'country_code' => $address->country_code,
            'province' => $address->province,
            'district' => $address->district,
            'address_line_1' => $address->address_line_1,
            'address_line_2' => $address->address_line_2,
            'postal_code' => $address->postal_code,
        ];
    }
}