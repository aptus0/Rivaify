<?php

namespace Modules\Commerce\Services\Order;

use App\Core\Tenancy\CurrentStore;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Enums\Checkout\CheckoutState;
use Modules\Commerce\Enums\Order\OrderAddressType;
use Modules\Commerce\Events\Order\OrderPlaced;
use Modules\Commerce\Exceptions\Order\InvalidOrderCreationException;
use Modules\Commerce\Models\Cart\CartItem;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Customer\CustomerAddress;
use Modules\Commerce\Models\Order\Order;

class OrderCreator
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly OrderNumberGenerator $numbers,
        private readonly OrderTimeline $timeline,
    ) {}

    public function create(CheckoutSession $checkout): Order
    {
        if ($checkout->store_id !== $this->currentStore->id()) {
            throw new InvalidOrderCreationException('Checkout does not belong to the current store.');
        }

        return DB::transaction(function () use ($checkout) {
            $checkout = CheckoutSession::query()
                ->with(['customer', 'shippingAddress', 'billingAddress', 'taxRate'])
                ->lockForUpdate()
                ->findOrFail($checkout->id);
            $existingOrder = Order::query()->where('checkout_id', $checkout->id)->first();
            if ($existingOrder !== null) {
                return $existingOrder;
            }
            if ($checkout->status !== CheckoutState::Processing) {
                throw new InvalidOrderCreationException('Checkout must be processing before an order can be created.');
            }
            if ($checkout->shippingAddress === null || $checkout->billingAddress === null) {
                throw new InvalidOrderCreationException('Checkout addresses are required to create an order.');
            }

            $cart = $checkout->cart()->with(['items.product', 'items.variant'])->first();
            if ($cart === null || $cart->items->isEmpty()) {
                throw new InvalidOrderCreationException('Checkout cart has no items.');
            }

            $order = Order::query()->create([
                'customer_id' => $checkout->customer_id,
                'checkout_id' => $checkout->id,
                'order_number' => $this->numbers->next(),
                'currency' => $checkout->currency,
                'subtotal' => $checkout->subtotal,
                'discount_total' => $checkout->discount_total,
                'tax_total' => $checkout->tax_total,
                'shipping_total' => $checkout->shipping_total,
                'grand_total' => $checkout->grand_total,
                'customer_email' => $checkout->email,
                'customer_phone' => $checkout->phone,
                'placed_at' => now(),
            ]);

            foreach ($cart->items as $item) {
                $this->snapshotItem($order, $item);
            }
            $this->snapshotAddress($order, $checkout->shippingAddress, OrderAddressType::Shipping);
            $this->snapshotAddress($order, $checkout->billingAddress, OrderAddressType::Billing);
            if ($checkout->tax_total !== '0.00') {
                $order->taxLines()->create([
                    'name' => $checkout->taxRate?->name ?? 'Tax',
                    'rate' => $checkout->taxRate?->rate ?? '0.00',
                    'amount' => $checkout->tax_total,
                ]);
            }

            $this->timeline->record($order, 'order_placed', 'Order placed.');
            OrderPlaced::dispatch($order);

            return $order;
        });
    }

    private function snapshotItem(Order $order, CartItem $item): void
    {
        $order->items()->create([
            'product_id' => $item->product_id,
            'variant_id' => $item->variant_id,
            'product_title' => $item->product?->title ?? 'Product',
            'variant_title' => $item->variant?->title,
            'sku' => $item->variant?->sku,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'discount_total' => $item->discount_amount,
            'tax_total' => $item->tax_amount,
            'line_total' => $item->line_total,
            'metadata' => $item->metadata,
        ]);
    }

    private function snapshotAddress(Order $order, CustomerAddress $address, OrderAddressType $type): void
    {
        $order->addresses()->create([
            'type' => $type,
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
        ]);
    }
}