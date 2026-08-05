<?php

namespace Modules\Commerce\Actions\Catalog;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\DTOs\Catalog\ProductOptionInputData;
use Modules\Commerce\Exceptions\Catalog\InvalidProductOptionsException;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Catalog\ProductOption;
use Modules\Commerce\Models\Catalog\ProductVariant;

/**
 * Rebuilds a product's variant set from its options (brief §5/§7/§29):
 * Color[Black,White] x Size[S,M] -> 4 variants. Syncs product_options /
 * product_option_values to match the input, then regenerates the
 * combinations.
 *
 * Existing variants whose combination is unchanged are reused as-is (their
 * price/sku/stock survive); combinations that no longer exist — including
 * the auto-created "Default" variant from CreateProduct, which has no
 * option values and so never matches once real options exist (brief §8)
 * — are soft-deleted. No orders reference variants yet in Sprint 2, so this
 * is safe; once Sprint 3 adds order line items, deleting a variant that has
 * been sold needs a different rule.
 */
class GenerateProductVariants
{
    /**
     * @param  ProductOptionInputData[]  $optionsInput
     * @return Collection<int, ProductVariant>
     */
    public function handle(Product $product, array $optionsInput): Collection
    {
        $this->validate($optionsInput);

        return DB::transaction(function () use ($product, $optionsInput) {
            $this->syncOptions($product, $optionsInput);

            $options = $product->options()->with('values')->get();
            $combinations = $this->cartesianProduct(
                $options->map(fn (ProductOption $option) => $option->values
                    ->map(fn ($value) => ['option_id' => $option->id, 'value_id' => $value->id, 'label' => $value->value])
                    ->all())->all()
            );

            $existingVariants = $product->variants()->with('variantValues')->get()
                ->keyBy(fn (ProductVariant $variant) => $this->signature(
                    $variant->variantValues->map(fn ($vv) => [
                        'option_id' => $vv->product_option_id,
                        'value_id' => $vv->product_option_value_id,
                    ])->all()
                ));

            $keptVariantIds = [];

            foreach ($combinations as $position => $combination) {
                $signature = $this->signature($combination);
                $variant = $existingVariants->get($signature);

                if ($variant === null) {
                    $variant = $product->variants()->create([
                        'title' => collect($combination)->pluck('label')->implode(' / '),
                        'is_taxable' => $product->is_taxable,
                        'requires_shipping' => $product->requires_shipping,
                        'position' => $position,
                    ]);

                    foreach ($combination as $pair) {
                        $variant->variantValues()->create([
                            'product_option_id' => $pair['option_id'],
                            'product_option_value_id' => $pair['value_id'],
                        ]);
                    }
                } elseif ($variant->position !== $position) {
                    $variant->update(['position' => $position]);
                }

                $keptVariantIds[] = $variant->id;
            }

            $product->variants()->whereNotIn('id', $keptVariantIds)->get()
                ->each(fn (ProductVariant $variant) => $variant->delete());

            return $product->variants()->with('variantValues')->orderBy('position')->get();
        });
    }

    /**
     * @param  ProductOptionInputData[]  $optionsInput
     */
    private function validate(array $optionsInput): void
    {
        if ($optionsInput === []) {
            throw new InvalidProductOptionsException('At least one option is required to generate variants.');
        }

        $names = [];
        foreach ($optionsInput as $option) {
            if (trim($option->name) === '') {
                throw new InvalidProductOptionsException('An option name cannot be empty.');
            }

            $hasEmptyValue = array_filter($option->values, fn ($value) => trim($value) === '') !== [];
            if ($option->values === [] || $hasEmptyValue) {
                throw new InvalidProductOptionsException("Option \"{$option->name}\" must have at least one non-empty value.");
            }

            $key = mb_strtolower($option->name);
            if (isset($names[$key])) {
                throw new InvalidProductOptionsException("Option \"{$option->name}\" is defined more than once.");
            }
            $names[$key] = true;
        }
    }

    /**
     * @param  ProductOptionInputData[]  $optionsInput
     */
    private function syncOptions(Product $product, array $optionsInput): void
    {
        $existingOptions = $product->options()->get()->keyBy('name');
        $seenOptionIds = [];

        foreach ($optionsInput as $position => $input) {
            $option = $existingOptions->pull($input->name) ?? $product->options()->make();
            $option->fill(['name' => $input->name, 'position' => $position]);
            $option->save();
            $seenOptionIds[] = $option->id;

            $existingValues = $option->values()->get()->keyBy('value');
            foreach ($input->values as $valuePosition => $value) {
                $valueModel = $existingValues->pull($value) ?? $option->values()->make();
                $valueModel->fill(['value' => $value, 'position' => $valuePosition]);
                $valueModel->save();
            }

            // Remaining values weren't in this call's input — merchant removed them.
            $existingValues->each(fn ($value) => $value->delete());
        }

        // Remaining options weren't in this call's input — merchant removed them.
        $existingOptions->each(fn (ProductOption $option) => $option->delete());
    }

    /**
     * @param  array<int, array{option_id: int, value_id: int}>  $pairs
     */
    private function signature(array $pairs): string
    {
        $parts = array_map(fn (array $pair) => "{$pair['option_id']}:{$pair['value_id']}", $pairs);
        sort($parts);

        return implode(',', $parts);
    }

    /**
     * @param  array<int, array>  $lists
     * @return array<int, array>
     */
    private function cartesianProduct(array $lists): array
    {
        return array_reduce(
            $lists,
            function (array $carry, array $list) {
                $result = [];
                foreach ($carry as $combination) {
                    foreach ($list as $item) {
                        $result[] = [...$combination, $item];
                    }
                }

                return $result;
            },
            [[]]
        );
    }
}
