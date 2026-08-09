<?php

namespace Modules\Commerce\Http\Presenters;

use Modules\Commerce\Enums\Payment\PaymentStatus;
use Modules\Commerce\Enums\Payment\PaymentTransactionStatus;
use Modules\Commerce\Enums\Payment\PaymentTransactionType;
use Modules\Commerce\Models\Fulfillment\Fulfillment;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Order\OrderAddress;
use Modules\Commerce\Models\Order\OrderEvent;
use Modules\Commerce\Models\Order\OrderItem;
use Modules\Commerce\Models\Order\OrderTaxLine;
use Modules\Commerce\Models\Payment\Payment;
use Modules\Commerce\Models\Payment\Refund;
use Modules\Commerce\Models\Returns\ReturnRequest;
use Modules\Commerce\Models\Shipping\Shipment;
use Modules\Commerce\ValueObjects\Money;

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
        $order->loadMissing([
            'customer',
            'items.variant',
            'addresses',
            'taxLines',
            'events',
            'payments.transactions',
            'fulfillments.items.orderItem.variant',
            'fulfillments.location',
            'fulfillments.shipments.events',
            'shipments.events',
            'returns.items.orderItem',
            'returns.refunds',
            'refunds',
        ]);

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
            'payments' => $order->payments->map(self::payment(...))->values()->all(),
            'fulfillments' => $order->fulfillments->map(self::fulfillment(...))->values()->all(),
            'shipments' => $order->shipments->map(self::shipment(...))->values()->all(),
            'returns' => $order->returns->map(self::return(...))->values()->all(),
            'refunds' => $order->refunds->map(self::refund(...))->values()->all(),
            'timeline' => $order->events->map(fn (OrderEvent $event): array => [
                'id' => $event->ulid,
                'type' => $event->type,
                'message' => $event->message,
                'created_at' => $event->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function payment(Payment $payment): array
    {
        $paymentTotal = Money::fromDecimal($payment->amount, $payment->currency);
        $refundedTotal = $payment->transactions
            ->filter(fn ($transaction): bool => (
                $transaction->type === PaymentTransactionType::Refund
                && $transaction->status === PaymentTransactionStatus::Succeeded
            ))
            ->reduce(
                fn (Money $total, $transaction): Money => $total->add(
                    Money::fromDecimal($transaction->amount, $payment->currency),
                ),
                Money::zero($payment->currency),
            );
        $refundPending = $payment->transactions->contains(fn ($transaction): bool => (
            $transaction->type === PaymentTransactionType::Refund
            && $transaction->status === PaymentTransactionStatus::Pending
        ));
        $refundable = ! $refundPending && in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)
            ? $paymentTotal->subtract($refundedTotal)
            : Money::zero($payment->currency);
        if ($refundable->amount < 0) {
            $refundable = Money::zero($payment->currency);
        }

        return [
            'id' => $payment->ulid,
            'provider' => $payment->provider,
            'status' => $payment->status->value,
            'amount' => $payment->amount,
            'refunded_amount' => $refundedTotal->toDecimal(),
            'refundable_amount' => $refundable->toDecimal(),
            'currency' => $payment->currency,
            'payment_method_type' => $payment->payment_method_type,
            'paid_at' => $payment->paid_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function fulfillment(Fulfillment $fulfillment): array
    {
        return [
            'id' => $fulfillment->ulid,
            'status' => $fulfillment->status->value,
            'location' => $fulfillment->location === null ? null : [
                'id' => $fulfillment->location->ulid,
                'name' => $fulfillment->location->name,
                'code' => $fulfillment->location->code,
            ],
            'package' => $fulfillment->package,
            'started_at' => $fulfillment->started_at?->toIso8601String(),
            'picked_at' => $fulfillment->picked_at?->toIso8601String(),
            'packed_at' => $fulfillment->packed_at?->toIso8601String(),
            'fulfilled_at' => $fulfillment->fulfilled_at?->toIso8601String(),
            'items' => $fulfillment->items->map(fn ($item): array => [
                'id' => $item->ulid,
                'order_item_id' => $item->orderItem->ulid,
                'title' => $item->orderItem->product_title,
                'variant_title' => $item->orderItem->variant_title,
                'sku' => $item->orderItem->sku,
                'barcode' => $item->orderItem->variant?->barcode,
                'quantity' => $item->quantity,
                'picked_quantity' => $item->picked_quantity,
                'status' => $item->status->value,
            ])->values()->all(),
            'shipments' => $fulfillment->shipments->map(self::shipment(...))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function shipment(Shipment $shipment): array
    {
        return [
            'id' => $shipment->ulid,
            'fulfillment_id' => $shipment->fulfillment?->ulid,
            'provider' => $shipment->provider,
            'tracking_number' => $shipment->tracking_number,
            'tracking_url' => $shipment->tracking_url,
            'status' => $shipment->status->value,
            'service_code' => $shipment->service_code,
            'package_weight' => $shipment->package_weight,
            'package_dimensions' => $shipment->package_dimensions,
            'shipped_at' => $shipment->shipped_at?->toIso8601String(),
            'delivered_at' => $shipment->delivered_at?->toIso8601String(),
            'events' => $shipment->events->map(fn ($event): array => [
                'id' => $event->ulid,
                'status' => $event->normalized_status->value,
                'location' => $event->location,
                'message' => $event->message,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function return(ReturnRequest $return): array
    {
        return [
            'id' => $return->ulid,
            'number' => $return->return_number,
            'status' => $return->status->value,
            'reason' => $return->reason,
            'customer_note' => $return->customer_note,
            'return_tracking_number' => $return->return_tracking_number,
            'requested_at' => $return->requested_at?->toIso8601String(),
            'approved_at' => $return->approved_at?->toIso8601String(),
            'received_at' => $return->received_at?->toIso8601String(),
            'completed_at' => $return->completed_at?->toIso8601String(),
            'items' => $return->items->map(fn ($item): array => [
                'id' => $item->ulid,
                'order_item_id' => $item->orderItem->ulid,
                'title' => $item->orderItem->product_title,
                'quantity' => $item->quantity,
                'reason_code' => $item->reason_code,
                'condition' => $item->condition,
                'resolution' => $item->resolution,
                'restock' => $item->restock,
            ])->values()->all(),
            'refunds' => $return->refunds->map(self::refund(...))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function refund(Refund $refund): array
    {
        return [
            'id' => $refund->ulid,
            'provider' => $refund->provider,
            'status' => $refund->status->value,
            'amount' => $refund->amount,
            'currency' => $refund->currency,
            'reason' => $refund->reason,
            'requested_at' => $refund->requested_at?->toIso8601String(),
            'completed_at' => $refund->completed_at?->toIso8601String(),
        ];
    }
}
