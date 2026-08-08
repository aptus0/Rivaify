<?php

namespace Modules\Commerce\Services\Checkout;

use App\Core\Tenancy\CurrentStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Commerce\DTOs\Customer\UpsertCustomerData;
use Modules\Commerce\Enums\Checkout\CheckoutState;
use Modules\Commerce\Enums\Discount\DiscountType;
use Modules\Commerce\Events\Checkout\CheckoutStarted;
use Modules\Commerce\Events\Checkout\CheckoutUpdated;
use Modules\Commerce\Exceptions\Checkout\CheckoutNotActiveException;
use Modules\Commerce\Exceptions\Checkout\CrossStoreCheckoutException;
use Modules\Commerce\Exceptions\Checkout\InvalidCheckoutTransitionException;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Customer\CustomerAddress;
use Modules\Commerce\Models\Shipping\ShippingMethod;
use Modules\Commerce\Services\Cart\CartManager;
use Modules\Commerce\Services\Customer\CustomerManager;
use Modules\Commerce\Services\Discount\DiscountEngine;
use Modules\Commerce\Services\Shipping\ShippingEngine;
use Modules\Commerce\Services\Tax\TaxEngine;
use Modules\Commerce\StateMachine\Checkout\CheckoutStateMachine;
use Modules\Commerce\ValueObjects\Money;

class CheckoutManager
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly CartManager $cartManager,
        private readonly CustomerManager $customerManager,
        private readonly DiscountEngine $discountEngine,
        private readonly CheckoutStateMachine $stateMachine,
        private readonly ShippingEngine $shippingEngine,
        private readonly TaxEngine $taxEngine,
    ) {}

    public function start(Cart $cart): CheckoutSession
    {
        $this->assertCartBelongsToCurrentStore($cart);
        $cart = $this->cartManager->recalculate($cart);

        return DB::transaction(function () use ($cart) {
            $checkout = $this->activeCheckouts()->where('cart_id', $cart->id)->lockForUpdate()->first();
            if ($checkout !== null) {
                return $this->syncCartTotals($checkout, $cart);
            }

            $checkout = CheckoutSession::query()->create([
                'cart_id' => $cart->id,
                'customer_id' => $cart->customer_id,
                'token' => (string) Str::ulid(),
                'subtotal' => $cart->subtotal,
                'discount_total' => $cart->discount_total,
                'shipping_total' => $cart->shipping_total,
                'tax_total' => $cart->tax_total,
                'tax_inclusive' => $cart->tax_inclusive,
                'tax_rate_id' => $cart->tax_rate_id,
                'grand_total' => $cart->grand_total,
                'currency' => $cart->currency,
                'expires_at' => now()->addMinutes(30),
            ]);

            CheckoutStarted::dispatch($checkout);

            return $checkout;
        });
    }

    public function findByToken(string $token): ?CheckoutSession
    {
        return $this->activeCheckouts()->where('token', $token)->first();
    }

    public function provideCustomerInformation(CheckoutSession $checkout, UpsertCustomerData $data): CheckoutSession
    {
        return DB::transaction(function () use ($checkout, $data) {
            $checkout = $this->lockActiveCheckout($checkout);
            if (! in_array($checkout->status, [CheckoutState::Initiated, CheckoutState::CustomerInformation], true)) {
                throw new InvalidCheckoutTransitionException('Customer information cannot be changed at this checkout step.');
            }

            $customer = $this->customerManager->findOrCreate($data);
            $checkout->update([
                'customer_id' => $customer->id,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ]);
            $checkout->cart()->update(['customer_id' => $customer->id]);

            if ($checkout->status === CheckoutState::Initiated) {
                $checkout = $this->stateMachine->transition($checkout, CheckoutState::CustomerInformation);
            } else {
                $checkout = $checkout->refresh();
            }

            CheckoutUpdated::dispatch($checkout);

            return $checkout;
        });
    }

    public function setAddresses(
        CheckoutSession $checkout,
        CustomerAddress $shippingAddress,
        ?CustomerAddress $billingAddress = null,
    ): CheckoutSession {
        return DB::transaction(function () use ($checkout, $shippingAddress, $billingAddress) {
            $checkout = $this->lockActiveCheckout($checkout);
            if (! in_array($checkout->status, [CheckoutState::CustomerInformation, CheckoutState::Address], true)) {
                throw new InvalidCheckoutTransitionException('Addresses cannot be changed at this checkout step.');
            }

            $shippingAddress = $this->resolveCustomerAddress($checkout, $shippingAddress);
            $billingAddress = $this->resolveCustomerAddress($checkout, $billingAddress ?? $shippingAddress);
            $checkout->update([
                'shipping_address_id' => $shippingAddress->id,
                'billing_address_id' => $billingAddress->id,
            ]);

            if ($checkout->status === CheckoutState::CustomerInformation) {
                $checkout = $this->stateMachine->transition($checkout, CheckoutState::Address);
            } else {
                $checkout = $checkout->refresh();
            }

            CheckoutUpdated::dispatch($checkout);

            return $checkout;
        });
    }

    public function selectShipping(CheckoutSession $checkout, ShippingMethod $method): CheckoutSession
    {
        return DB::transaction(function () use ($checkout, $method) {
            $checkout = $this->lockActiveCheckout($checkout);
            if (! in_array($checkout->status, [CheckoutState::Address, CheckoutState::Shipping], true)) {
                throw new InvalidCheckoutTransitionException('Shipping cannot be selected at this checkout step.');
            }

            $cart = $checkout->cart()->lockForUpdate()->first();
            $address = $checkout->shippingAddress;
            if ($cart === null || $address === null) {
                throw new InvalidCheckoutTransitionException('A cart and shipping address are required before selecting shipping.');
            }

            $quote = $this->shippingEngine->quote($cart, $address, $method);
            $shippingTotal = $this->checkoutHasFreeShippingDiscount($checkout)
                ? Money::zero($checkout->currency)
                : $quote->amount;
            $cart->update(['shipping_total' => $shippingTotal->toDecimal()]);
            $cart = $this->cartManager->recalculate($cart);
            $checkout->update([
                'shipping_method_id' => $quote->method->id,
                'subtotal' => $cart->subtotal,
                'discount_total' => $cart->discount_total,
                'tax_total' => $cart->tax_total,
                'tax_inclusive' => $cart->tax_inclusive,
                'tax_rate_id' => $cart->tax_rate_id,
                'shipping_total' => $cart->shipping_total,
                'grand_total' => $cart->grand_total,
                'currency' => $cart->currency,
            ]);

            if ($checkout->status === CheckoutState::Address) {
                $checkout = $this->stateMachine->transition($checkout, CheckoutState::Shipping);
            } else {
                $checkout = $checkout->refresh();
            }

            CheckoutUpdated::dispatch($checkout);

            return $checkout;
        });
    }

    public function applyDiscount(CheckoutSession $checkout, string $code): CheckoutSession
    {
        return DB::transaction(function () use ($checkout, $code) {
            $checkout = $this->lockActiveCheckout($checkout);
            if (! in_array($checkout->status, [
                CheckoutState::Initiated,
                CheckoutState::CustomerInformation,
                CheckoutState::Address,
                CheckoutState::Shipping,
            ], true)) {
                throw new InvalidCheckoutTransitionException('Discounts cannot be changed at this checkout step.');
            }

            $cart = $checkout->cart()->lockForUpdate()->first();
            if ($cart === null) {
                throw new CrossStoreCheckoutException('Checkout cart does not belong to the current store.');
            }

            $cart = $this->discountEngine->apply($cart, $code)->load('discount');
            if ($checkout->shippingAddress !== null) {
                $cart = $this->taxEngine->apply($cart, $checkout->shippingAddress)->load('discount');
            }
            $shippingTotal = $cart->discount?->type === DiscountType::FreeShipping
                ? Money::zero($cart->currency)
                : Money::fromDecimal($checkout->shipping_total, $cart->currency);
            $cart->update(['shipping_total' => $shippingTotal->toDecimal()]);
            $cart = $this->cartManager->recalculate($cart);
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

            $checkout = $checkout->refresh();
            CheckoutUpdated::dispatch($checkout);

            return $checkout;
        });
    }

    public function applyTax(CheckoutSession $checkout): CheckoutSession
    {
        return DB::transaction(function () use ($checkout) {
            $checkout = $this->lockActiveCheckout($checkout);
            if (! in_array($checkout->status, [CheckoutState::Address, CheckoutState::Shipping], true)) {
                throw new InvalidCheckoutTransitionException('Tax cannot be calculated at this checkout step.');
            }

            $cart = $checkout->cart()->lockForUpdate()->first();
            $address = $checkout->shippingAddress;
            if ($cart === null || $address === null) {
                throw new InvalidCheckoutTransitionException('A cart and shipping address are required before calculating tax.');
            }

            $cart = $this->taxEngine->apply($cart, $address);
            $cart->update(['shipping_total' => $checkout->shipping_total]);
            $cart = $this->cartManager->recalculate($cart);
            $checkout->update([
                'subtotal' => $cart->subtotal,
                'discount_total' => $cart->discount_total,
                'tax_total' => $cart->tax_total,
                'tax_inclusive' => $cart->tax_inclusive,
                'tax_rate_id' => $cart->tax_rate_id,
                'shipping_total' => $cart->shipping_total,
                'grand_total' => $cart->grand_total,
                'currency' => $cart->currency,
            ]);

            $checkout = $checkout->refresh();
            CheckoutUpdated::dispatch($checkout);

            return $checkout;
        });
    }

    public function expire(CheckoutSession $checkout): CheckoutSession
    {
        return DB::transaction(function () use ($checkout) {
            $checkout = $this->lockCheckout($checkout);
            if ($checkout->status === CheckoutState::Expired) {
                return $checkout;
            }

            if (! $this->stateMachine->canTransition($checkout->status, CheckoutState::Expired)) {
                throw new InvalidCheckoutTransitionException('Completed checkouts cannot expire.');
            }

            return $this->stateMachine->transition($checkout, CheckoutState::Expired);
        });
    }

    private function activeCheckouts()
    {
        return CheckoutSession::query()
            ->whereNotIn('status', [CheckoutState::Completed->value, CheckoutState::Expired->value])
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    private function lockActiveCheckout(CheckoutSession $checkout): CheckoutSession
    {
        $checkout = $this->lockCheckout($checkout);

        if ($checkout->expires_at !== null && $checkout->expires_at->isPast()) {
            if ($this->stateMachine->canTransition($checkout->status, CheckoutState::Expired)) {
                $this->stateMachine->transition($checkout, CheckoutState::Expired);
            }

            throw new CheckoutNotActiveException('Checkout has expired.');
        }

        if (in_array($checkout->status, [CheckoutState::Completed, CheckoutState::Expired], true)) {
            throw new CheckoutNotActiveException('Checkout is no longer active.');
        }

        return $checkout;
    }

    private function lockCheckout(CheckoutSession $checkout): CheckoutSession
    {
        $this->assertCheckoutBelongsToCurrentStore($checkout);

        $checkout = CheckoutSession::query()->lockForUpdate()->find($checkout->id);
        if ($checkout === null) {
            throw new CrossStoreCheckoutException('Checkout does not belong to the current store.');
        }

        return $checkout;
    }

    private function resolveCustomerAddress(CheckoutSession $checkout, CustomerAddress $address): CustomerAddress
    {
        if ($checkout->customer_id === null || $address->store_id !== $checkout->store_id) {
            throw new CrossStoreCheckoutException('Checkout address does not belong to this store.');
        }

        $address = CustomerAddress::query()
            ->where('customer_id', $checkout->customer_id)
            ->find($address->id);
        if ($address === null) {
            throw new CrossStoreCheckoutException('Checkout address does not belong to this customer.');
        }

        return $address;
    }

    private function syncCartTotals(CheckoutSession $checkout, Cart $cart): CheckoutSession
    {
        $checkout->update([
            'discount_id' => $cart->discount_id,
            'discount_code' => $cart->discount_code,
            'subtotal' => $cart->subtotal,
            'discount_total' => $cart->discount_total,
            'shipping_total' => $cart->shipping_total,
            'tax_total' => $cart->tax_total,
            'tax_inclusive' => $cart->tax_inclusive,
            'tax_rate_id' => $cart->tax_rate_id,
            'grand_total' => $cart->grand_total,
            'currency' => $cart->currency,
        ]);

        return $checkout->refresh();
    }

    private function calculateGrandTotal(
        string $subtotal,
        string $discountTotal,
        string $taxTotal,
        bool $taxInclusive,
        string $shippingTotal,
        string $currency,
    ): Money {
        return Money::fromDecimal($subtotal, $currency)
            ->subtract(Money::fromDecimal($discountTotal, $currency))
            ->add($taxInclusive ? Money::zero($currency) : Money::fromDecimal($taxTotal, $currency))
            ->add(Money::fromDecimal($shippingTotal, $currency));
    }

    private function checkoutHasFreeShippingDiscount(CheckoutSession $checkout): bool
    {
        return $checkout->discount()->first()?->type === DiscountType::FreeShipping;
    }

    private function assertCartBelongsToCurrentStore(Cart $cart): void
    {
        if ($cart->store_id !== $this->currentStore->id()) {
            throw new CrossStoreCheckoutException('Cart does not belong to the current store.');
        }
    }

    private function assertCheckoutBelongsToCurrentStore(CheckoutSession $checkout): void
    {
        if ($checkout->store_id !== $this->currentStore->id()) {
            throw new CrossStoreCheckoutException('Checkout does not belong to the current store.');
        }
    }
}