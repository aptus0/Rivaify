<?php

namespace Modules\Commerce\Services\Tax;

use Illuminate\Support\Facades\DB;
use Modules\Commerce\Enums\Tax\TaxRateStatus;
use Modules\Commerce\Events\Tax\TaxApplied;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Cart\CartItem;
use Modules\Commerce\Models\Customer\CustomerAddress;
use Modules\Commerce\Models\Tax\TaxRate;
use Modules\Commerce\Services\Cart\CartManager;
use Modules\Commerce\ValueObjects\Money;
use Modules\Commerce\ValueObjects\TaxCalculation;

class TaxEngine
{
    public function __construct(private readonly CartManager $cartManager) {}

    public function quote(Cart $cart, CustomerAddress $address): TaxCalculation
    {
        $this->assertStoreOwnership($cart, $address);
        $rate = TaxRate::query()
            ->whereRaw('upper(country_code) = ?', [mb_strtoupper($address->country_code)])
            ->where('status', TaxRateStatus::Active->value)
            ->orderBy('id')
            ->first();
        if ($rate === null) {
            return new TaxCalculation(null, [], Money::zero($cart->currency));
        }

        $rateBasisPoints = Money::fromDecimal($rate->rate, $cart->currency)->amount;
        $itemTaxes = [];
        $total = Money::zero($cart->currency);

        foreach ($cart->items()->with('variant')->get() as $item) {
            $tax = $this->calculateItemTax($item, $rate, $rateBasisPoints, $cart->currency);
            $itemTaxes[$item->id] = $tax;
            $total = $total->add($tax);
        }

        return new TaxCalculation($rate, $itemTaxes, $total);
    }

    public function apply(Cart $cart, CustomerAddress $address): Cart
    {
        return DB::transaction(function () use ($cart, $address) {
            $cart = Cart::query()->lockForUpdate()->findOrFail($cart->id);
            $calculation = $this->quote($cart, $address);
            $items = $cart->items()->lockForUpdate()->get();

            foreach ($items as $item) {
                $item->update([
                    'tax_amount' => ($calculation->itemTaxes[$item->id] ?? Money::zero($cart->currency))->toDecimal(),
                ]);
            }

            $cart->update([
                'tax_inclusive' => $calculation->isInclusive(),
                'tax_rate_id' => $calculation->rate?->id,
            ]);
            $cart = $this->cartManager->recalculate($cart);
            TaxApplied::dispatch($cart);

            return $cart;
        });
    }

    private function calculateItemTax(CartItem $item, TaxRate $rate, int $rateBasisPoints, string $currency): Money
    {
        if ($item->variant === null || ! $item->variant->is_taxable) {
            return Money::zero($currency);
        }

        $taxableAmount = Money::fromDecimal($item->unit_price, $currency)
            ->multiply($item->quantity)
            ->subtract(Money::fromDecimal($item->discount_amount, $currency));
        if ($taxableAmount->amount <= 0) {
            return Money::zero($currency);
        }

        return $rate->is_inclusive
            ? $taxableAmount->multiplyRatio($rateBasisPoints, 10_000 + $rateBasisPoints)
            : $taxableAmount->percentage($rate->rate);
    }

    private function assertStoreOwnership(Cart $cart, CustomerAddress $address): void
    {
        if ($cart->store_id !== $address->store_id) {
            throw new \InvalidArgumentException('Cart and tax address belong to different stores.');
        }
    }
}