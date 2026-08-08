<?php

namespace Modules\Commerce\Services\Discount;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Enums\Discount\DiscountConditionType;
use Modules\Commerce\Enums\Discount\DiscountStatus;
use Modules\Commerce\Enums\Discount\DiscountType;
use Modules\Commerce\Events\Discount\DiscountApplied;
use Modules\Commerce\Exceptions\Discount\DiscountNotApplicableException;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Cart\CartItem;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Customer\Customer;
use Modules\Commerce\Models\Discount\Discount;
use Modules\Commerce\Models\Discount\DiscountUsage;
use Modules\Commerce\Services\Cart\CartManager;
use Modules\Commerce\ValueObjects\DiscountApplication;
use Modules\Commerce\ValueObjects\Money;

class DiscountEngine
{
    public function __construct(private readonly CartManager $cartManager) {}

    public function quote(Cart $cart, string $code): DiscountApplication
    {
        $discount = Discount::query()
            ->with('conditions')
            ->whereRaw('upper(code) = ?', [mb_strtoupper(trim($code))])
            ->first();
        if ($discount === null) {
            throw new DiscountNotApplicableException('Discount code was not found.');
        }

        $this->assertAvailable($discount, $cart);
        $eligibleItems = $this->eligibleItems($cart->loadMissing('items'), $discount);
        if ($eligibleItems->isEmpty()) {
            throw new DiscountNotApplicableException('Discount does not apply to any cart item.');
        }

        $itemDiscounts = $this->calculateItemDiscounts($cart, $discount, $eligibleItems);
        $total = array_reduce(
            $itemDiscounts,
            fn (Money $total, Money $amount): Money => $total->add($amount),
            Money::zero($cart->currency),
        );

        return new DiscountApplication(
            discount: $discount,
            itemDiscounts: $itemDiscounts,
            itemDiscountTotal: $total,
            grantsFreeShipping: $discount->type === DiscountType::FreeShipping,
        );
    }

    public function apply(Cart $cart, string $code): Cart
    {
        return DB::transaction(function () use ($cart, $code) {
            $cart = Cart::query()->lockForUpdate()->findOrFail($cart->id);
            $application = $this->quote($cart, $code);
            $items = $cart->items()->lockForUpdate()->get()->keyBy('id');

            foreach ($items as $item) {
                $item->update([
                    'discount_amount' => ($application->itemDiscounts[$item->id] ?? Money::zero($cart->currency))->toDecimal(),
                ]);
            }

            $cart->update([
                'discount_id' => $application->discount->id,
                'discount_code' => $application->discount->code,
            ]);
            $cart = $this->cartManager->recalculate($cart);
            DiscountApplied::dispatch($cart, $application->discount);

            return $cart;
        });
    }

    public function clear(Cart $cart): Cart
    {
        return DB::transaction(function () use ($cart) {
            $cart = Cart::query()->lockForUpdate()->findOrFail($cart->id);
            $cart->items()->lockForUpdate()->update(['discount_amount' => 0]);
            $cart->update(['discount_id' => null, 'discount_code' => null]);

            return $this->cartManager->recalculate($cart);
        });
    }

    public function recordUsage(Discount $discount, ?Customer $customer, ?CheckoutSession $checkout, ?int $orderId = null): DiscountUsage
    {
        return DB::transaction(function () use ($discount, $customer, $checkout, $orderId) {
            $discount = Discount::query()->lockForUpdate()->findOrFail($discount->id);
            if ($checkout !== null) {
                $existingUsage = DiscountUsage::query()
                    ->where('discount_id', $discount->id)
                    ->where('checkout_id', $checkout->id)
                    ->first();
                if ($existingUsage !== null) {
                    return $existingUsage;
                }
            }

            $this->assertUsageAvailable($discount);
            $usage = DiscountUsage::query()->create([
                'discount_id' => $discount->id,
                'customer_id' => $customer?->id,
                'checkout_id' => $checkout?->id,
                'order_id' => $orderId,
            ]);
            $discount->increment('usage_count');

            return $usage;
        });
    }

    private function assertAvailable(Discount $discount, Cart $cart): void
    {
        if ($discount->status !== DiscountStatus::Active) {
            throw new DiscountNotApplicableException('Discount code is inactive.');
        }
        if ($discount->starts_at !== null && $discount->starts_at->isFuture()) {
            throw new DiscountNotApplicableException('Discount code has not started yet.');
        }
        if ($discount->ends_at !== null && $discount->ends_at->isPast()) {
            throw new DiscountNotApplicableException('Discount code has expired.');
        }
        $this->assertUsageAvailable($discount);

        $cartTotal = Money::fromDecimal($cart->subtotal, $cart->currency);
        if (
            $discount->minimum_purchase !== null
            && $cartTotal->isLessThan(Money::fromDecimal($discount->minimum_purchase, $cart->currency))
        ) {
            throw new DiscountNotApplicableException('Discount minimum purchase has not been reached.');
        }

        foreach ($discount->conditions->where('type', DiscountConditionType::CartTotal) as $condition) {
            $amount = Money::fromDecimal((string) ($condition->value['amount'] ?? ''), $cart->currency);
            if (! $this->matchesAmountCondition($cartTotal, $condition->operator, $amount)) {
                throw new DiscountNotApplicableException('Discount cart total condition has not been met.');
            }
        }
    }

    private function assertUsageAvailable(Discount $discount): void
    {
        if ($discount->usage_limit !== null && $discount->usage_count >= $discount->usage_limit) {
            throw new DiscountNotApplicableException('Discount usage limit has been reached.');
        }
    }

    /**
     * @return Collection<int, CartItem>
     */
    private function eligibleItems(Cart $cart, Discount $discount): Collection
    {
        $productIds = $discount->conditions
            ->filter(fn ($condition): bool => in_array($condition->type, [DiscountConditionType::Products, DiscountConditionType::Collections], true))
            ->flatMap(fn ($condition): array => $condition->value['product_ids'] ?? [])
            ->map(fn (mixed $productId): int => (int) $productId)
            ->filter()
            ->unique();

        if ($productIds->isEmpty()) {
            return $cart->items;
        }

        return $cart->items->whereIn('product_id', $productIds->all());
    }

    /**
     * @param  Collection<int, CartItem>  $eligibleItems
     * @return array<int, Money>
     */
    private function calculateItemDiscounts(Cart $cart, Discount $discount, Collection $eligibleItems): array
    {
        if ($discount->type === DiscountType::FreeShipping) {
            return [];
        }

        $lineTotals = $eligibleItems->mapWithKeys(fn (CartItem $item): array => [
            $item->id => Money::fromDecimal($item->unit_price, $cart->currency)->multiply($item->quantity),
        ]);

        return match ($discount->type) {
            DiscountType::Percentage => $this->percentageDiscounts($lineTotals, $discount, $cart),
            DiscountType::FixedAmount => $this->fixedAmountDiscounts($lineTotals, $discount, $cart),
            DiscountType::FreeShipping => [],
        };
    }

    /**
     * @param  Collection<int, Money>  $lineTotals
     * @return array<int, Money>
     */
    private function percentageDiscounts(Collection $lineTotals, Discount $discount, Cart $cart): array
    {
        $percentage = (string) $discount->value;
        if (Money::fromDecimal($percentage, $cart->currency)->isGreaterThan(Money::fromDecimal('100.00', $cart->currency))) {
            throw new DiscountNotApplicableException('Percentage discounts cannot exceed 100 percent.');
        }

        return $lineTotals
            ->map(fn (Money $lineTotal): Money => $lineTotal->percentage($percentage))
            ->all();
    }

    /**
     * @param  Collection<int, Money>  $lineTotals
     * @return array<int, Money>
     */
    private function fixedAmountDiscounts(Collection $lineTotals, Discount $discount, Cart $cart): array
    {
        $eligibleTotal = $lineTotals->reduce(
            fn (Money $total, Money $lineTotal): Money => $total->add($lineTotal),
            Money::zero($cart->currency),
        );
        $requested = Money::fromDecimal($discount->value, $cart->currency);
        $discountTotal = $requested->isGreaterThan($eligibleTotal) ? $eligibleTotal : $requested;
        $positiveLines = $lineTotals->filter(fn (Money $lineTotal): bool => $lineTotal->amount > 0);
        if ($positiveLines->isEmpty()) {
            throw new DiscountNotApplicableException('Discount cannot be applied to zero-value cart items.');
        }

        $allocated = $discountTotal->allocate($positiveLines->map(fn (Money $lineTotal): int => $lineTotal->amount)->all());
        $amounts = [];
        foreach ($positiveLines->keys()->values() as $index => $itemId) {
            $amounts[$itemId] = $allocated[$index];
        }

        return $amounts;
    }

    private function matchesAmountCondition(Money $actual, ?string $operator, Money $expected): bool
    {
        return match ($operator) {
            '>', 'gt' => $actual->isGreaterThan($expected),
            '>=', 'gte', null => ! $actual->isLessThan($expected),
            '<', 'lt' => $actual->isLessThan($expected),
            '<=', 'lte' => ! $actual->isGreaterThan($expected),
            '=', '==', 'eq' => ! $actual->isLessThan($expected) && ! $actual->isGreaterThan($expected),
            default => throw new DiscountNotApplicableException('Discount condition operator is not supported.'),
        };
    }
}