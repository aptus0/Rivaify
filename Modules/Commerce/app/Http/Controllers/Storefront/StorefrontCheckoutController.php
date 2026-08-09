<?php

namespace Modules\Commerce\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Commerce\DTOs\Customer\CustomerAddressData;
use Modules\Commerce\DTOs\Customer\UpsertCustomerData;
use Modules\Commerce\Enums\Customer\CustomerAddressType;
use Modules\Commerce\Exceptions\Cart\CartItemNotPurchasableException;
use Modules\Commerce\Exceptions\Checkout\CheckoutNotActiveException;
use Modules\Commerce\Exceptions\Checkout\InvalidCheckoutTransitionException;
use Modules\Commerce\Exceptions\Discount\DiscountNotApplicableException;
use Modules\Commerce\Exceptions\Inventory\InsufficientInventoryException;
use Modules\Commerce\Exceptions\Payment\IdempotencyInProgressException;
use Modules\Commerce\Exceptions\Payment\IdempotencyKeyConflictException;
use Modules\Commerce\Exceptions\Payment\PaymentGatewayNotConfiguredException;
use Modules\Commerce\Exceptions\Shipping\ShippingMethodNotAvailableException;
use Modules\Commerce\Http\Presenters\CheckoutPresenter;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Shipping\ShippingMethod;
use Modules\Commerce\Services\Cart\CartManager;
use Modules\Commerce\Services\Checkout\CheckoutManager;
use Modules\Commerce\Services\Checkout\CheckoutOrchestrator;
use Modules\Commerce\Services\Customer\CustomerManager;
use Modules\Commerce\Services\Shipping\ShippingEngine;

class StorefrontCheckoutController extends Controller
{
    private const CART_COOKIE_NAME = 'rivaify_cart';

    public function start(Request $request, CartManager $carts, CheckoutManager $checkouts): JsonResponse
    {
        $cart = $carts->getOrCreate($request->cookie(self::CART_COOKIE_NAME));

        return response()
            ->json(['data' => CheckoutPresenter::present($checkouts->start($cart))], 201)
            ->withCookie(cookie(
                self::CART_COOKIE_NAME,
                $cart->token,
                60 * 24 * 30,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'lax',
            ));
    }

    public function show(string $token): JsonResponse
    {
        return response()->json(['data' => CheckoutPresenter::present($this->checkout($token))]);
    }

    public function updateCustomer(Request $request, string $token, CheckoutManager $checkouts): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:100'],
            'first_name' => ['required', 'string', 'max:30'],
            'last_name' => ['required', 'string', 'max:30'],
            'phone' => ['required', 'string', 'max:20'],
            'accepts_marketing' => ['nullable', 'boolean'],
        ]);

        return $this->perform(fn () => $checkouts->provideCustomerInformation(
            $this->checkout($token),
            new UpsertCustomerData(
                email: $validated['email'],
                firstName: $validated['first_name'] ?? null,
                lastName: $validated['last_name'] ?? null,
                phone: $validated['phone'] ?? null,
                acceptsMarketing: $validated['accepts_marketing'] ?? null,
            ),
        ));
    }

    public function updateAddresses(
        Request $request,
        string $token,
        CustomerManager $customers,
        CheckoutManager $checkouts,
    ): JsonResponse {
        $validated = $request->validate([
            'shipping' => ['required', 'array'],
            'shipping.first_name' => ['required', 'string', 'max:255'],
            'shipping.last_name' => ['required', 'string', 'max:255'],
            'shipping.company' => ['nullable', 'string', 'max:255'],
            'shipping.phone' => ['nullable', 'string', 'max:64'],
            'shipping.country_code' => ['required', 'string', 'size:2'],
            'shipping.province' => ['nullable', 'string', 'max:255'],
            'shipping.district' => ['nullable', 'string', 'max:255'],
            'shipping.address_line_1' => ['required', 'string', 'max:255'],
            'shipping.address_line_2' => ['nullable', 'string', 'max:255'],
            'shipping.postal_code' => ['nullable', 'string', 'max:32'],
            'billing_same_as_shipping' => ['nullable', 'boolean'],
            'billing' => ['required_if:billing_same_as_shipping,false', 'nullable', 'array'],
            'billing.first_name' => ['required_with:billing', 'string', 'max:255'],
            'billing.last_name' => ['required_with:billing', 'string', 'max:255'],
            'billing.company' => ['nullable', 'string', 'max:255'],
            'billing.phone' => ['nullable', 'string', 'max:64'],
            'billing.country_code' => ['required_with:billing', 'string', 'size:2'],
            'billing.province' => ['nullable', 'string', 'max:255'],
            'billing.district' => ['nullable', 'string', 'max:255'],
            'billing.address_line_1' => ['required_with:billing', 'string', 'max:255'],
            'billing.address_line_2' => ['nullable', 'string', 'max:255'],
            'billing.postal_code' => ['nullable', 'string', 'max:32'],
        ]);
        $checkout = $this->checkout($token);
        if ($checkout->customer === null) {
            return response()->json(['message' => 'customer_information_required'], 422);
        }

        return $this->perform(function () use ($validated, $checkout, $customers, $checkouts) {
            $shipping = $customers->createAddress(
                $checkout->customer,
                $this->addressData($validated['shipping'], CustomerAddressType::Shipping),
            );
            $billing = ($validated['billing_same_as_shipping'] ?? true)
                ? $shipping
                : $customers->createAddress(
                    $checkout->customer,
                    $this->addressData($validated['billing'], CustomerAddressType::Billing),
                );

            return $checkouts->setAddresses($checkout, $shipping, $billing);
        });
    }

    public function shippingQuotes(string $token, ShippingEngine $shipping): JsonResponse
    {
        $checkout = $this->checkout($token);
        if ($checkout->shippingAddress === null) {
            return response()->json(['message' => 'shipping_address_required'], 422);
        }

        $quotes = $shipping->quotes($checkout->cart, $checkout->shippingAddress);

        return response()->json(['data' => $quotes->map(fn ($quote): array => [
            'id' => $quote->method->ulid,
            'name' => $quote->method->name,
            'type' => $quote->method->type->value,
            'amount' => $quote->amount->toDecimal(),
            'currency' => $quote->amount->currency,
            'estimated_days_min' => $quote->method->estimated_days_min,
            'estimated_days_max' => $quote->method->estimated_days_max,
        ])->values()]);
    }

    public function selectShipping(Request $request, string $token, CheckoutManager $checkouts): JsonResponse
    {
        $validated = $request->validate(['shipping_method_id' => ['required', 'string', 'size:26']]);
        $method = ShippingMethod::query()->where('ulid', $validated['shipping_method_id'])->firstOrFail();

        return $this->perform(fn () => $checkouts->selectShipping($this->checkout($token), $method));
    }

    public function applyDiscount(Request $request, string $token, CheckoutManager $checkouts): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:128']]);

        return $this->perform(fn () => $checkouts->applyDiscount($this->checkout($token), $validated['code']));
    }

    public function applyTax(string $token, CheckoutManager $checkouts): JsonResponse
    {
        return $this->perform(fn () => $checkouts->applyTax($this->checkout($token)));
    }

    public function pay(Request $request, string $token, CheckoutOrchestrator $orchestrator): JsonResponse
    {
        $allowedProviders = collect(config('commerce.payments.storefront_providers', ['paytr']))
            ->map(fn (mixed $provider): string => mb_strtolower(trim((string) $provider)))
            ->filter(fn (string $provider): bool => $provider !== '')
            // The synchronous manual gateway is a test helper. It requires an
            // explicit opt-in even when listed so it cannot silently become a
            // live storefront payment method because of environment drift.
            ->filter(fn (string $provider): bool => $provider !== 'manual' || config('commerce.payments.allow_manual_storefront') === true)
            ->values()
            ->all();
        $validated = $request->validate([
            'provider' => ['required', 'string', 'max:64', Rule::in($allowedProviders)],
            'payment_method_type' => ['nullable', 'string', 'max:64'],
        ]);
        $idempotencyKey = $request->header('Idempotency-Key');
        if (! is_string($idempotencyKey) || trim($idempotencyKey) === '' || mb_strlen($idempotencyKey) > 255) {
            return response()->json(['message' => 'idempotency_key_required'], 422);
        }

        try {
            $payment = $orchestrator->pay(
                $this->checkout($token),
                $validated['provider'],
                $validated['payment_method_type'] ?? null,
                $idempotencyKey,
            );
        } catch (IdempotencyInProgressException|IdempotencyKeyConflictException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (
            CartItemNotPurchasableException|
            DiscountNotApplicableException|
            InsufficientInventoryException|
            InvalidCheckoutTransitionException|
            PaymentGatewayNotConfiguredException|
            ShippingMethodNotAvailableException $exception
        ) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => [
            'id' => $payment->ulid,
            'status' => $payment->status->value,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'order_id' => $payment->order?->ulid,
            'checkout' => CheckoutPresenter::present($this->checkout($token)),
            'gateway' => [
                'provider' => $payment->provider,
                'iframe_url' => $payment->metadata['iframe_url'] ?? null,
            ],
        ]]);
    }

    public function confirmation(string $token): JsonResponse
    {
        $checkout = $this->checkout($token)->load('order');
        if ($checkout->order === null) {
            return response()->json(['message' => 'order_not_completed'], 409);
        }

        return response()->json(['data' => CheckoutPresenter::present($checkout)]);
    }

    private function checkout(string $token): CheckoutSession
    {
        return CheckoutSession::query()->where('token', $token)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function addressData(array $address, CustomerAddressType $type): CustomerAddressData
    {
        return new CustomerAddressData(
            type: $type,
            firstName: $address['first_name'],
            lastName: $address['last_name'],
            countryCode: $address['country_code'],
            addressLine1: $address['address_line_1'],
            company: $address['company'] ?? null,
            phone: $address['phone'] ?? null,
            province: $address['province'] ?? null,
            district: $address['district'] ?? null,
            addressLine2: $address['address_line_2'] ?? null,
            postalCode: $address['postal_code'] ?? null,
        );
    }

    /**
     * @param  callable(): CheckoutSession  $operation
     */
    private function perform(callable $operation): JsonResponse
    {
        try {
            return response()->json(['data' => CheckoutPresenter::present($operation())]);
        } catch (
            CheckoutNotActiveException|
            InvalidCheckoutTransitionException|
            DiscountNotApplicableException|
            ShippingMethodNotAvailableException|
            InsufficientInventoryException $exception
        ) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
