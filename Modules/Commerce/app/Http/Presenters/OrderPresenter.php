<?php

namespace Modules\Commerce\Http\Presenters;

use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Order\OrderAddress;
use Modules\Commerce\Models\Order\OrderEvent;
use Modules\Commerce\Models\Order\OrderItem;
use Modules\Commerce\Models\Order\OrderTaxLine;
use Modules\Commerce\Models\Payment\Payment;

class OrderPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Order $order): array
    {
        $order->loadMissing('customer');

        return [
            'id' => $order->ulid,
            'number' => $order->order_number,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'fulfillment_status' => $order->fulfillment_status->value,
            'customer' => $order->customer === null ? [
                'name' => null,
                'email' => $order->customer_email,
            ] : [
                'id' => $order->customer->ulid,
                'name' => trim("{$order->customer->first_name} {$order->customer->last_name}"),
                'email' => $order->customer->email,
            ],
            'currency' => $order->currency,
            'grand_total' => $order->grand_total,
            'placed_at' => $order->placed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Order $order): array
    {
        $order->loadMissing(['customer', 'items', 'addresses', 'taxLines', 'events', 'payments']);

        return [
            ...self::summary($order),
            'customer_phone' => $order->customer_phone,
            'subtotal' => $order->subtotal,
            'discount_total' => $order->discount_total,
            'tax_total' => $order->tax_total,
            'shipping_total' => $order->shipping_total,
            'notes' => $order->notes,
            'items' => $order->items->map(fn (OrderItem $item): array => [
                'id' => $item->ulid,
                'product_title' => $item->product_title,
                'variant_title' => $item->variant_title,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_total' => $item->discount_total,
                'tax_total' => $item->tax_total,
                'line_total' => $item->line_total,
            ])->values()->all(),
            'addresses' => $order->addresses->map(fn (OrderAddress $address): array => [
                'type' => $address->type->value,
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
            ])->values()->all(),
            'tax_lines' => $order->taxLines->map(fn (OrderTaxLine $line): array => [
                'name' => $line->name,
                'rate' => $line->rate,
                'amount' => $line->amount,
            ])->values()->all(),
            'payments' => $order->payments->map(fn (Payment $payment): array => [
                'id' => $payment->ulid,
                'provider' => $payment->provider,
                'status' => $payment->status->value,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'payment_method_type' => $payment->payment_method_type,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ])->values()->all(),
            'timeline' => $order->events->map(fn (OrderEvent $event): array => [
                'id' => $event->ulid,
                'type' => $event->type,
                'message' => $event->message,
                'created_at' => $event->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}