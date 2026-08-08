<?php

namespace Modules\Commerce\Services\Pricing;

use App\Core\Tenancy\CurrentStore;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Exceptions\Cart\CartItemNotPurchasableException;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Catalog\ProductVariant;
use Modules\Commerce\Services\Cart\CartManager;
use Modules\Commerce\ValueObjects\Money;

class PricingEngine
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly CartManager $carts,
    ) {}

    public function refresh(Cart $cart): Cart
    {
        if ($cart->store_id !== $this->currentStore->id()) {
            throw new CartItemNotPurchasableException('Cart does not belong to the current store.');
        }

        return DB::transaction(function () use ($cart) {
            $cart = Cart::query()->lockForUpdate()->findOrFail($cart->id);
            $items = $cart->items()->lockForUpdate()->get();

            foreach ($items as $item) {
                $variant = ProductVariant::query()->with('product')->find($item->variant_id);
                if (
                    $variant === null
                    || $variant->status !== ProductStatus::Active
                    || $variant->product === null
                    || $variant->product->status !== ProductStatus::Active
                ) {
                    throw new CartItemNotPurchasableException('A cart item is no longer available for purchase.');
                }

                $price = Money::fromDecimal($variant->price, $cart->currency);
                $item->update([
                    'product_id' => $variant->product_id,
                    'unit_price' => $price->toDecimal(),
                    'original_price' => $price->toDecimal(),
                ]);
            }

            return $this->carts->recalculate($cart);
        });
    }
}