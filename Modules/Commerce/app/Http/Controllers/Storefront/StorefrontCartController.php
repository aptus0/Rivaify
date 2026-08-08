<?php

namespace Modules\Commerce\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Exceptions\Cart\CartItemNotPurchasableException;
use Modules\Commerce\Exceptions\Cart\CartNotActiveException;
use Modules\Commerce\Exceptions\Cart\InvalidCartQuantityException;
use Modules\Commerce\Exceptions\Inventory\InsufficientInventoryException;
use Modules\Commerce\Http\Presenters\CartPresenter;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Catalog\ProductVariant;
use Modules\Commerce\Services\Cart\CartManager;

class StorefrontCartController extends Controller
{
    private const COOKIE_NAME = 'rivaify_cart';

    public function show(Request $request, CartManager $carts): JsonResponse
    {
        return $this->respond($request, $this->cart($request, $carts));
    }

    public function create(Request $request, CartManager $carts): JsonResponse
    {
        return $this->respond($request, $this->cart($request, $carts), 201);
    }

    public function addItem(Request $request, CartManager $carts): JsonResponse
    {
        $validated = $request->validate([
            'variant_id' => ['required', 'string', 'size:26'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);
        $cart = $this->cart($request, $carts);
        $variant = ProductVariant::query()->where('ulid', $validated['variant_id'])->firstOrFail();

        try {
            $carts->addItem($cart, $variant, $validated['quantity']);
        } catch (CartItemNotPurchasableException|CartNotActiveException|InvalidCartQuantityException|InsufficientInventoryException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return $this->respond($request, $cart->fresh());
    }

    public function updateItem(Request $request, string $itemUlid, CartManager $carts): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
        ]);
        $cart = $this->cart($request, $carts);
        $item = $cart->items()->where('ulid', $itemUlid)->firstOrFail();

        try {
            $carts->updateQuantity($cart, $item, $validated['quantity']);
        } catch (CartItemNotPurchasableException|CartNotActiveException|InvalidCartQuantityException|InsufficientInventoryException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return $this->respond($request, $cart->fresh());
    }

    public function removeItem(Request $request, string $itemUlid, CartManager $carts): JsonResponse
    {
        $cart = $this->cart($request, $carts);
        $item = $cart->items()->where('ulid', $itemUlid)->firstOrFail();
        $carts->removeItem($cart, $item);

        return $this->respond($request, $cart->fresh());
    }

    public function clear(Request $request, CartManager $carts): JsonResponse
    {
        $cart = $this->cart($request, $carts);

        return $this->respond($request, $carts->clear($cart));
    }

    private function cart(Request $request, CartManager $carts): Cart
    {
        return $carts->getOrCreate($request->cookie(self::COOKIE_NAME));
    }

    private function respond(Request $request, Cart $cart, int $status = 200): JsonResponse
    {
        return response()
            ->json(['data' => CartPresenter::present($cart)], $status)
            ->withCookie(cookie(
                self::COOKIE_NAME,
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
}