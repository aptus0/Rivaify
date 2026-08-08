<?php

namespace Modules\Commerce\Http\Presenters;

use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Cart\CartItem;

class CartPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(Cart $cart): array
    {
        $cart->loadMissing(['items.product', 'items.variant', 'discount']);

        return [
            'id' => $cart->ulid,
            'status' => $cart->status->value,
            'currency' => $cart->currency,
            'items' => $cart->items->map(fn (CartItem $item): array => self::item($item))->values()->all(),
            'subtotal' => $cart->subtotal,
            'discount_total' => $cart->discount_total,
            'tax_total' => $cart->tax_total,
            'tax_inclusive' => $cart->tax_inclusive,
            'shipping_total' => $cart->shipping_total,
            'grand_total' => $cart->grand_total,
            'discount' => $cart->discount === null ? null : [
                'code' => $cart->discount_code,
                'type' => $cart->discount->type->value,
            ],
            'expires_at' => $cart->expires_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function item(CartItem $item): array
    {
        return [
            'id' => $item->ulid,
            'product' => [
                'id' => $item->product?->ulid,
                'title' => $item->product?->title,
                'slug' => $item->product?->slug,
            ],
            'variant' => [
                'id' => $item->variant?->ulid,
                'title' => $item->variant?->title,
                'sku' => $item->variant?->sku,
            ],
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'original_price' => $item->original_price,
            'discount_amount' => $item->discount_amount,
            'tax_amount' => $item->tax_amount,
            'line_total' => $item->line_total,
        ];
    }
}