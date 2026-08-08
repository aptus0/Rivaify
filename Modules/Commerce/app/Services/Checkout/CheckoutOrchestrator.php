<?php

namespace Modules\Commerce\Services\Checkout;

use App\Core\Tenancy\CurrentStore;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Enums\Cart\CartStatus;
use Modules\Commerce\Enums\Checkout\CheckoutState;
use Modules\Commerce\Enums\Discount\DiscountType;
use Modules\Commerce\Enums\Order\PaymentStatus as OrderPaymentStatus;
use Modules\Commerce\Enums\Payment\PaymentStatus;
use Modules\Commerce\Exceptions\Checkout\InvalidCheckoutTransitionException;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Order\OrderNotificationOutbox;
use Modules\Commerce\Models\Payment\Payment;
use Modules\Commerce\Services\Discount\DiscountEngine;
use Modules\Commerce\Services\Inventory\InventoryManager;
use Modules\Commerce\Services\Order\OrderCreator;
use Modules\Commerce\Services\Order\OrderTimeline;
use Modules\Commerce\Services\Payment\IdempotencyService;
use Modules\Commerce\Services\Payment\PaymentManager;
use Modules\Commerce\Services\Pricing\PricingEngine;
use Modules\Commerce\Services\Shipping\ShippingEngine;
use Modules\Commerce\Services\Tax\TaxEngine;
use Modules\Commerce\StateMachine\Checkout\CheckoutStateMachine;
use Modules\Commerce\ValueObjects\Money;

class CheckoutOrchestrator
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly CheckoutStateMachine $stateMachine,
        private readonly InventoryManager $inventory,
        private readonly PaymentManager $payments,
        private readonly OrderCreator $orders,
        private readonly OrderTimeline $timeline,
        private readonly DiscountEngine $discounts,
        private readonly PricingEngine $pricing,
        private readonly ShippingEngine $shipping,
        private readonly TaxEngine $taxes,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function pay(
        CheckoutSession $checkout,
        string $provider,
        ?string $paymentMethodType,
        string $idempotencyKey,
    ): Payment {
        $this->assertCheckoutBelongsToCurrentStore($checkout);
        $record = $this->idempotency->claim($idempotencyKey, 'checkout_payment', [
            'checkout' => $checkout->ulid,
            'provider' => mb_strtolower(trim($provider)),
            'payment_method_type' => $paymentMethodType,
        ]);
        if ($record->status === 'completed' && isset($record->response['payment_id'])) {
            return Payment::query()->findOrFail($record->response['payment_id']);
        }

        $reservationCreated = false;
        $payment = null;

        try {
            $checkout = $this->validateReadyForPayment($checkout);
            $this->inventory->reserveForCheckout($checkout);
            $reservationCreated = true;
            $checkout = $this->enterPaymentState($checkout);
            $payment = $this->payments->createPayment($checkout, $provider, $paymentMethodType);
            $payment = match ($payment->status) {
                PaymentStatus::Paid => $this->completePaidPayment($payment),
                PaymentStatus::Failed => $this->failPayment($payment),
                default => $payment,
            };

            $this->idempotency->complete($record, [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'payment_status' => $payment->status->value,
            ]);

            return $payment;
        } catch (\Throwable $exception) {
            if ($payment === null && $reservationCreated) {
                $this->inventory->releaseForCheckout($checkout);
            }
            $this->idempotency->fail($record, $exception->getMessage());

            throw $exception;
        }
    }

    public function completePaidPayment(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== PaymentStatus::Paid) {
                throw new \InvalidArgumentException('Only paid payments can complete a checkout.');
            }
            if ($payment->order_id !== null) {
                return $payment;
            }

            $checkout = CheckoutSession::query()->lockForUpdate()->findOrFail($payment->checkout_id);
            $this->assertCheckoutBelongsToCurrentStore($checkout);
            $this->assertPaymentAmountMatchesCheckout($payment, $checkout);
            if ($checkout->status === CheckoutState::Payment) {
                $checkout = $this->stateMachine->transition($checkout, CheckoutState::Processing);
            }
            if ($checkout->status !== CheckoutState::Processing) {
                throw new InvalidCheckoutTransitionException('Paid payment does not belong to a processable checkout.');
            }

            $order = $this->orders->create($checkout);
            $this->inventory->commitForCheckout($checkout);
            $payment->update(['order_id' => $order->id]);
            $order->update(['payment_status' => OrderPaymentStatus::Paid]);
            OrderNotificationOutbox::query()->firstOrCreate([
                'order_id' => $order->id,
                'type' => 'customer_payment_confirmation',
            ], [
                'store_id' => $order->store_id,
            ]);
            $this->timeline->record($order, 'payment_succeeded', 'Payment was successfully received.', metadata: [
                'payment_id' => $payment->ulid,
                'provider' => $payment->provider,
            ]);
            $this->timeline->record($order, 'inventory_committed', 'Inventory reservation was committed.');

            if ($checkout->discount_id !== null) {
                $discount = $checkout->discount()->first();
                if ($discount !== null) {
                    $this->discounts->recordUsage($discount, $checkout->customer, $checkout, $order->id);
                }
            }

            $checkout->cart()->update(['status' => CartStatus::Converted]);
            $this->stateMachine->transition($checkout, CheckoutState::Completed);

            return $payment->refresh();
        });
    }

    public function failPayment(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $checkout = CheckoutSession::query()->lockForUpdate()->findOrFail($payment->checkout_id);
            $this->assertCheckoutBelongsToCurrentStore($checkout);

            if ($checkout->status === CheckoutState::Payment) {
                $this->stateMachine->transition($checkout, CheckoutState::Failed);
            }
            $this->inventory->releaseForCheckout($checkout);

            return $payment->refresh();
        });
    }

    private function validateReadyForPayment(CheckoutSession $checkout): CheckoutSession
    {
        return DB::transaction(function () use ($checkout) {
            $checkout = CheckoutSession::query()->lockForUpdate()->findOrFail($checkout->id);
            $this->assertCheckoutBelongsToCurrentStore($checkout);
            if (! in_array($checkout->status, [CheckoutState::Shipping, CheckoutState::Failed], true)) {
                throw new InvalidCheckoutTransitionException('Checkout is not ready for payment.');
            }
            if (
                $checkout->customer_id === null
                || $checkout->shipping_address_id === null
                || $checkout->billing_address_id === null
                || $checkout->shipping_method_id === null
            ) {
                throw new InvalidCheckoutTransitionException('Checkout requires customer, address, and shipping details before payment.');
            }

            return $this->refreshFinancialSnapshot($checkout);
        });
    }

    private function refreshFinancialSnapshot(CheckoutSession $checkout): CheckoutSession
    {
        $cart = $checkout->cart()->lockForUpdate()->firstOrFail();
        $address = $checkout->shippingAddress;
        $method = $checkout->shippingMethod;
        if ($address === null || $method === null) {
            throw new InvalidCheckoutTransitionException('Checkout requires shipping details before payment.');
        }

        $cart = $this->pricing->refresh($cart);
        if ($cart->discount_code !== null) {
            $cart = $this->discounts->apply($cart, $cart->discount_code)->load('discount');
        } else {
            $cart->load('discount');
        }
        $cart = $this->taxes->apply($cart, $address)->load('discount');
        $quote = $this->shipping->quote($cart, $address, $method);
        $shippingTotal = $cart->discount?->type === DiscountType::FreeShipping
            ? Money::zero($cart->currency)
            : $quote->amount;
        $cart->update(['shipping_total' => $shippingTotal->toDecimal()]);
        $cart = $this->pricing->refresh($cart);

        $checkout->update([
            'discount_id' => $cart->discount_id,
            'discount_code' => $cart->discount_code,
            'subtotal' => $cart->subtotal,
            'discount_total' => $cart->discount_total,
            'tax_total' => $cart->tax_total,
            'tax_inclusive' => $cart->tax_inclusive,
            'tax_rate_id' => $cart->tax_rate_id,
            'shipping_total' => $cart->shipping_total,
            'grand_total' => $cart->grand_total,
            'currency' => $cart->currency,
        ]);

        return $checkout->refresh();
    }

    private function enterPaymentState(CheckoutSession $checkout): CheckoutSession
    {
        return DB::transaction(function () use ($checkout) {
            $checkout = CheckoutSession::query()->lockForUpdate()->findOrFail($checkout->id);
            if (! in_array($checkout->status, [CheckoutState::Shipping, CheckoutState::Failed], true)) {
                throw new InvalidCheckoutTransitionException('Checkout cannot enter payment from its current state.');
            }

            return $this->stateMachine->transition($checkout, CheckoutState::Payment);
        });
    }

    private function assertPaymentAmountMatchesCheckout(Payment $payment, CheckoutSession $checkout): void
    {
        $paymentAmount = Money::fromDecimal($payment->amount, $payment->currency);
        $checkoutAmount = Money::fromDecimal($checkout->grand_total, $checkout->currency);
        if (
            $paymentAmount->currency !== $checkoutAmount->currency
            || $paymentAmount->isLessThan($checkoutAmount)
            || $paymentAmount->isGreaterThan($checkoutAmount)
        ) {
            throw new \InvalidArgumentException('Payment amount does not match checkout total.');
        }
    }

    private function assertCheckoutBelongsToCurrentStore(CheckoutSession $checkout): void
    {
        if ($checkout->store_id !== $this->currentStore->id()) {
            throw new \InvalidArgumentException('Checkout does not belong to the current store.');
        }
    }
}