<?php

namespace Modules\Commerce\Services\Storefront;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Catalog\ProductCollection;

class StorefrontCatalogService
{
    public function __construct(private readonly ProductStorefrontEligibility $eligibility) {}

    /**
     * @return Collection<int, Product>
     */
    public function latestProducts(int $limit = 12): Collection
    {
        return $this->baseProductQuery()
            ->latest('published_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Product>
     */
    public function collectionProducts(string $collectionUlid, int $limit = 12): Collection
    {
        $collection = ProductCollection::query()
            ->where('ulid', $collectionUlid)
            ->first();

        if (! $collection) {
            return $this->latestProducts($limit);
        }

        return $this->baseProductQuery()
            ->whereHas('collections', fn ($query) => $query->whereKey($collection->id))
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function productsForSource(?array $source = null, int $limit = 12): array
    {
        $type = $source['type'] ?? 'latest_products';
        $products = $type === 'collection' && is_string($source['collectionId'] ?? null)
            ? $this->collectionProducts($source['collectionId'], $limit)
            : $this->latestProducts($limit);

        return $products->map(fn (Product $product): array => $this->present($product))->values()->all();
    }

    public function productBySlug(string $slug): Product
    {
        /** @var Product $product */
        $product = $this->baseProductQuery()
            ->with('options.values')
            ->where('slug', $slug)
            ->firstOrFail();

        return $product;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    private function baseProductQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->eligibility->apply(
            Product::query()
                ->with([
                    'media',
                    'variants' => fn ($query) => $query
                        ->where('status', ProductStatus::Active->value)
                        ->with('inventoryItem.levels'),
                ])
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Product $product, bool $includeOptions = false): array
    {
        return [
            'id' => $product->ulid,
            'title' => $product->title,
            'slug' => $product->slug,
            'description' => $product->description,
            'is_taxable' => $product->is_taxable,
            'requires_shipping' => $product->requires_shipping,
            'media' => $product->media->map(fn ($media): array => [
                'id' => $media->ulid,
                'url' => "/api/storefront/v1/products/{$product->slug}/media/{$media->ulid}",
                'alt_text' => $media->alt_text,
                'width' => $media->width,
                'height' => $media->height,
                'is_featured' => $media->is_featured,
            ])->values()->all(),
            'variants' => $product->variants->map(function ($variant): array {
                $inventory = $variant->inventoryItem;
                $sellable = $inventory?->is_tracked
                    ? $inventory->levels->sum(fn ($level): int => max($level->sellableQuantity(), 0))
                    : null;

                return [
                    'id' => $variant->ulid,
                    'title' => $variant->title,
                    'sku' => $variant->sku,
                    'price' => $variant->price,
                    'compare_at_price' => $variant->compare_at_price,
                    'available' => $sellable === null || $inventory->allow_oversell || $sellable > 0,
                    'available_quantity' => $inventory?->allow_oversell ? null : $sellable,
                ];
            })->values()->all(),
            'options' => ! $includeOptions ? null : $product->options->map(fn ($option): array => [
                'name' => $option->name,
                'values' => $option->values->pluck('value')->values()->all(),
            ])->values()->all(),
        ];
    }
}
