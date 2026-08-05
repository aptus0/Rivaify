<?php

namespace Modules\Commerce\Actions\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Commerce\DTOs\Catalog\CreateProductData;
use Modules\Commerce\Events\Catalog\ProductCreated;
use Modules\Commerce\Models\Catalog\Product;

/**
 * Every product gets a variant, even one with no options (brief §8) — this
 * is what lets checkout (Sprint 3) always operate on variant_id and never
 * product_id.
 */
class CreateProduct
{
    public function handle(CreateProductData $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = Product::query()->create([
                'title' => $data->title,
                'slug' => $this->uniqueSlug($data->title),
                'description' => $data->description,
                'product_type' => $data->productType,
                'vendor' => $data->vendor,
                'is_taxable' => $data->isTaxable,
                'requires_shipping' => $data->requiresShipping,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $product->variants()->create([
                'title' => 'Default',
                'is_taxable' => $data->isTaxable,
                'requires_shipping' => $data->requiresShipping,
            ]);

            ProductCreated::dispatch($product);

            return $product;
        });
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 2;

        while (Product::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
