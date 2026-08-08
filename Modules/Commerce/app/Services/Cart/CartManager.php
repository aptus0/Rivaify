<?php

namespace Modules\Commerce\Services\Cart;

use App\Core\Tenancy\CurrentStore;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Commerce\Enums\Cart\CartStatus;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Events\Cart\CartCreated;
use Modules\Commerce\Events\Cart\CartItemAdded;
use Modules\Commerce\Exceptions\Cart\CartItemNotPurchasableException;
use Modules\Commerce\Exceptions\Cart\CartNotActiveException;
use Modules\Commerce\Exceptions\Cart\CrossStoreCartException;
use Modules\Commerce\Exceptions\Cart\InvalidCartQuantityException;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Cart\CartItem;
use Modules\Commerce\Models\Catalog\ProductVariant;
use Modules\Commerce\Services\Inventory\InventoryManager;
use Modules\Commerce\ValueObjects\Money;

class CartManager
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly InventoryManager $inventory,
    ) {}

    public function getOrCreate(?string $token = null, ?User $user = null, string $currency = 'TRY'): Cart
    {
        $currency = Money::zero($currency)->currency;

        return DB::transaction(function () use ($token, $user, $currency) {
            if ($token !== null && trim($token) !== '') {
                $cart = $this->activeCarts()->where('token', $token)->lockForUpdate()->first();
                if ($cart !== null) {
                    return $cart;
                }
            }

            if ($user !== null) {
                $cart = $this->activeCarts()->where('user_id', $user->id)->lockForUpdate()->first();
                if ($cart !== null) {
                    return $cart;
                }
            }

            $cart = Cart::query()->create([
                'user_id' => $user?->id,
                'token' => (string) Str::ulid(),
                'currency' => $currency,
                'expires_at' => now()->addDays(30),
            ]);

            CartCreated::dispatch($cart);

            return $cart;
        });
    }

    public function addItem(Cart $cart, ProductVariant $variant, int $quantity): CartItem
    {
        $this->assertPositiveQuantity($quantity);

        return DB::transaction(function () use ($cart, $variant, $quantity) {
            $cart = $this->lockActiveCart($cart);
            $variant = $this->resolvePurchasableVariant($cart, $variant);
            $item = $cart->items()->where('variant_id', $variant->id)->lockForUpdate()->first()
                ?? $cart->items()->make([
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->id,
                    'quantity' => 0,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                ]);

            $newQuantity = $item->quantity + $quantity;
            $this->assertVariantQuantityAvailable($variant, $newQuantity);
            $this->setItemQuantityAndPrice($item, $variant, $newQuantity, $cart->currency);
            $this->recalculateLocked($cart);

            $item = $item->refresh();
            CartItemAdded::dispatch($cart->refresh(), $item);

            return $item;
        });
    }

    public function updateQuantity(Cart $cart, CartItem $item, int $quantity): ?CartItem
    {
        if ($quantity < 0) {
            throw new InvalidCartQuantityException('Cart item quantity cannot be negative.');
        }

        if ($quantity === 0) {
            $this->removeItem($cart, $item);

            return null;
        }

        return DB::transaction(function () use ($cart, $item, $quantity) {
            $cart = $this->lockActiveCart($cart);
            $item = $this->lockCartItem($cart, $item);
            $variant = $this->resolvePurchasableVariant($cart, ProductVariant::query()->findOrFail($item->variant_id));

            $this->assertVariantQuantityAvailable($variant, $quantity);
            $this->setItemQuantityAndPrice($item, $variant, $quantity, $cart->currency);
            $this->recalculateLocked($cart);

            return $item->refresh();
        });
    }

    public function removeItem(Cart $cart, CartItem $item): void
    {
        DB::transaction(function () use ($cart, $item) {
            $cart = $this->lockActiveCart($cart);
            $this->lockCartItem($cart, $item)->delete();
            $this->recalculateLocked($cart);
        });
    }

    public function clear(Cart $cart): Cart
    {
        return DB::transaction(function () use ($cart) {
            $cart = $this->lockActiveCart($cart);
            $cart->items()->delete();

            return $this->recalculateLocked($cart);
        });
    }

    public function merge(Cart $guestCart, Cart $customerCart): Cart
    {
        $this->assertCartBelongsToCurrentStore($guestCart);
        $this->assertCartBelongsToCurrentStore($customerCart);

        if ($guestCart->is($customerCart)) {
            return $this->recalculate($guestCart);
        }

        return DB::transaction(function () use ($guestCart, $customerCart) {
            $carts = Cart::query()
                ->whereKey([$guestCart->id, $customerCart->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $guestCart = $carts->get($guestCart->id);
            $customerCart = $carts->get($customerCart->id);
            if ($guestCart === null || $customerCart === null) {
                throw new CrossStoreCartException('Both carts must belong to the current store.');
            }

            $this->assertActive($guestCart);
            $this->assertActive($customerCart);

            foreach ($guestCart->items()->lockForUpdate()->get() as $guestItem) {
                $variant = $this->resolvePurchasableVariant(
                    $customerCart,
                    ProductVariant::query()->findOrFail($guestItem->variant_id),
                );
                $customerItem = $customerCart->items()
                    ->where('variant_id', $variant->id)
                    ->lockForUpdate()
                    ->first();

                if ($customerItem === null) {
                    $this->assertVariantQuantityAvailable($variant, $guestItem->quantity);
                    $guestItem->update(['cart_id' => $customerCart->id]);

                    continue;
                }

                $newQuantity = $customerItem->quantity + $guestItem->quantity;
                $this->assertVariantQuantityAvailable($variant, $newQuantity);
                $this->setItemQuantityAndPrice(
                    $customerItem,
                    $variant,
                    $newQuantity,
                    $customerCart->currency,
                );
                $guestItem->delete();
            }

            $guestCart->update(['status' => CartStatus::Abandoned]);
            $this->recalculateLocked($guestCart);

            return $this->recalculateLocked($customerCart);
        });
    }

    public function recalculate(Cart $cart): Cart
    {
        return DB::transaction(fn () => $this->recalculateLocked($this->lockActiveCart($cart)));
    }

    private function activeCarts()
    {
        return Cart::query()
            ->where('status', CartStatus::Active->value)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    private function lockActiveCart(Cart $cart): Cart
    {
        $this->assertCartBelongsToCurrentStore($cart);

        $lockedCart = Cart::query()->lockForUpdate()->find($cart->id);
        if ($lockedCart === null) {
            throw new CrossStoreCartException('Cart does not belong to the current store.');
        }

        $this->assertActive($lockedCart);

        return $lockedCart;
    }

    private function lockCartItem(Cart $cart, CartItem $item): CartItem
    {
        $lockedItem = $cart->items()->whereKey($item->id)->lockForUpdate()->first();
        if ($lockedItem === null) {
            throw new InvalidArgumentException('Cart item does not belong to this cart.');
        }

        return $lockedItem;
    }

    private function resolvePurchasableVariant(Cart $cart, ProductVariant $variant): ProductVariant
    {
        if ($variant->store_id !== $cart->store_id) {
            throw new CartItemNotPurchasableException('The variant does not belong to this cart store.');
        }

        $variant = ProductVariant::query()->with('product')->find($variant->id);
        if (
            $variant === null
            || $variant->status !== ProductStatus::Active
            || $variant->product === null
            || $variant->product->status !== ProductStatus::Active
        ) {
            throw new CartItemNotPurchasableException('The selected variant is not available for purchase.');
        }

        return $variant;
    }

    private function setItemQuantityAndPrice(CartItem $item, ProductVariant $variant, int $quantity, string $currency): void
    {
        $price = Money::fromDecimal($variant->price, $currency);
        $lineTotal = $price->multiply($quantity);

        $item->fill([
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'quantity' => $quantity,
            'unit_price' => $price->toDecimal(),
            'original_price' => $price->toDecimal(),
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_total' => $lineTotal->toDecimal(),
        ]);
        $item->save();
    }

    private function recalculateLocked(Cart $cart): Cart
    {
        $subtotal = Money::zero($cart->currency);
        $discountTotal = Money::zero($cart->currency);
        $taxTotal = Money::zero($cart->currency);

        foreach ($cart->items()->lockForUpdate()->get() as $item) {
            $lineSubtotal = Money::fromDecimal($item->unit_price, $cart->currency)->multiply($item->quantity);
            $discount = Money::fromDecimal($item->discount_amount, $cart->currency);
            $tax = Money::fromDecimal($item->tax_amount, $cart->currency);
            $lineTotal = $lineSubtotal
                ->subtract($discount)
                ->add($cart->tax_inclusive ? Money::zero($cart->currency) : $tax);

            $item->update(['line_total' => $lineTotal->toDecimal()]);
            $subtotal = $subtotal->add($lineSubtotal);
            $discountTotal = $discountTotal->add($discount);
            $taxTotal = $taxTotal->add($tax);
        }

        $shippingTotal = Money::fromDecimal($cart->shipping_total, $cart->currency);
        $grandTotal = $subtotal
            ->subtract($discountTotal)
            ->add($cart->tax_inclusive ? Money::zero($cart->currency) : $taxTotal)
            ->add($shippingTotal);

        $cart->update([
            'subtotal' => $subtotal->toDecimal(),
            'discount_total' => $discountTotal->toDecimal(),
            'tax_total' => $taxTotal->toDecimal(),
            'grand_total' => $grandTotal->toDecimal(),
        ]);

        return $cart->refresh();
    }

    private function assertCartBelongsToCurrentStore(Cart $cart): void
    {
        if ($cart->store_id !== $this->currentStore->id()) {
            throw new CrossStoreCartException('Cart does not belong to the current store.');
        }
    }

    private function assertActive(Cart $cart): void
    {
        if ($cart->status !== CartStatus::Active || ($cart->expires_at !== null && $cart->expires_at->isPast())) {
            throw new CartNotActiveException('Cart is no longer active.');
        }
    }

    private function assertPositiveQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidCartQuantityException('Cart item quantity must be at least one.');
        }
    }

    private function assertVariantQuantityAvailable(ProductVariant $variant, int $quantity): void
    {
        if ($this->inventory->sellableQuantity($variant) < $quantity) {
            throw new \Modules\Commerce\Exceptions\Inventory\InsufficientInventoryException(
                'Requested quantity exceeds sellable inventory.'
            );
        }
    }
}