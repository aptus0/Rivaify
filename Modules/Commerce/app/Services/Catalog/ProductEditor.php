<?php

namespace Modules\Commerce\Services\Catalog;

use App\Core\Tenancy\CurrentStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Commerce\Actions\Catalog\GenerateProductVariants;
use Modules\Commerce\DTOs\Catalog\ProductEditorData;
use Modules\Commerce\DTOs\Catalog\ProductVariantEditorData;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Events\Catalog\ProductCreated;
use Modules\Commerce\Events\Catalog\ProductUpdated;
use Modules\Commerce\Exceptions\Catalog\CrossStoreAssignmentException;
use Modules\Commerce\Exceptions\Catalog\InvalidProductVariantDataException;
use Modules\Commerce\Models\Catalog\Brand;
use Modules\Commerce\Models\Catalog\Category;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Catalog\ProductVariant;
use Modules\Commerce\Models\Inventory\InventoryLocation;
use Modules\Commerce\Services\Inventory\InventoryManager;

class ProductEditor
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly GenerateProductVariants $variantGenerator,
        private readonly InventoryManager $inventory,
    ) {}

    public function create(ProductEditorData $data): Product
    {
        return DB::transaction(function () use ($data) {
            $this->assertOrganizationOwnership($data);
            $this->assertSuppliedVariantCodesAreUnique($data);

            $product = Product::query()->create([
                ...$this->productAttributes($data),
                'slug' => $this->uniqueSlug($data->slug ?? $data->title),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
            $product->variants()->create($this->defaultVariantAttributes($data));
            $this->syncProductStructure($product, $data);
            $this->syncTags($product, $data->tags);

            $product = $this->loadProduct($product);
            ProductCreated::dispatch($product);

            return $product;
        });
    }

    public function update(Product $product, ProductEditorData $data): Product
    {
        $this->assertProductBelongsToCurrentStore($product);

        return DB::transaction(function () use ($product, $data) {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $this->assertOrganizationOwnership($data);
            $this->assertSuppliedVariantCodesAreUnique($data);

            $product->update([
                ...$this->productAttributes($data),
                'slug' => $this->uniqueSlug($data->slug ?? $data->title, $product),
                'updated_by' => auth()->id(),
            ]);
            $this->syncProductStructure($product, $data);
            $this->syncTags($product, $data->tags);

            $product = $this->loadProduct($product);
            ProductUpdated::dispatch($product);

            return $product;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function productAttributes(ProductEditorData $data): array
    {
        $isPhysical = $data->productType->value === 'physical';

        return [
            'title' => trim($data->title),
            'description' => $this->nullableText($data->description),
            'category_id' => $data->categoryId,
            'brand_id' => $data->brandId,
            'product_type' => $data->productType,
            'status' => $data->status,
            'vendor' => $this->nullableText($data->vendor),
            'is_taxable' => $data->isTaxable,
            'requires_shipping' => $isPhysical && $data->requiresShipping,
            'published_at' => $data->status === ProductStatus::Active ? now() : null,
            'meta_title' => $this->nullableText($data->metaTitle),
            'meta_description' => $this->nullableText($data->metaDescription),
            'package_width' => $isPhysical ? $data->packageWidth : null,
            'package_height' => $isPhysical ? $data->packageHeight : null,
            'package_length' => $isPhysical ? $data->packageLength : null,
            'package_dimension_unit' => $data->packageDimensionUnit,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultVariantAttributes(ProductEditorData $data): array
    {
        return [
            'title' => 'Default',
            'status' => $data->status,
            'is_taxable' => $data->isTaxable,
            'requires_shipping' => $data->productType->value === 'physical' && $data->requiresShipping,
        ];
    }

    private function syncProductStructure(Product $product, ProductEditorData $data): void
    {
        $variants = $data->options === []
            ? $this->syncDefaultVariant($product, $data)
            : $this->variantGenerator->handle($product, $data->options);
        $inputsByTitle = collect($data->variants)->keyBy(fn (ProductVariantEditorData $variant): string => $variant->title);

        foreach ($variants as $variant) {
            $input = $inputsByTitle->get($variant->title) ?? $this->fallbackVariantData($variant, $data);
            $this->assertVariantCodeAvailable(
                $variant,
                $this->nullableText($input->sku),
                $this->nullableText($input->barcode),
            );
            $variant->update([
                'sku' => $this->nullableText($input->sku),
                'barcode' => $this->nullableText($input->barcode),
                'price' => $input->price,
                'compare_at_price' => $input->compareAtPrice,
                'cost_price' => $input->costPrice,
                'weight' => $data->productType->value === 'physical' ? $input->weight : null,
                'weight_unit' => $input->weightUnit,
                'requires_shipping' => $data->productType->value === 'physical' && $input->requiresShipping,
                'is_taxable' => $input->isTaxable,
                'status' => $input->status,
            ]);

            $this->inventory->setTracking($variant, $input->trackInventory, $input->allowOversell);
            if ($input->trackInventory) {
                $this->syncInventoryLevels($variant, $input->inventoryByLocationId);
            }
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ProductVariant>
     */
    private function syncDefaultVariant(Product $product, ProductEditorData $data)
    {
        $defaultVariant = $product->variants()
            ->withTrashed()
            ->whereDoesntHave('variantValues')
            ->orderBy('id')
            ->first();
        if ($defaultVariant === null) {
            $defaultVariant = $product->variants()->create($this->defaultVariantAttributes($data));
        } elseif ($defaultVariant->trashed()) {
            $defaultVariant->restore();
        }

        $product->options()->get()->each->delete();
        $product->variants()->whereKeyNot($defaultVariant->id)->get()->each->delete();

        return $product->variants()->whereKey($defaultVariant->id)->get();
    }

    private function fallbackVariantData(ProductVariant $variant, ProductEditorData $data): ProductVariantEditorData
    {
        return new ProductVariantEditorData(
            title: $variant->title,
            price: (string) $variant->price,
            compareAtPrice: $variant->compare_at_price,
            costPrice: $variant->cost_price,
            sku: $variant->sku,
            barcode: $variant->barcode,
            weight: $variant->weight,
            weightUnit: $variant->weight_unit,
            requiresShipping: $data->productType->value === 'physical' && $data->requiresShipping,
            isTaxable: $data->isTaxable,
            status: $data->status,
            trackInventory: $variant->inventoryItem?->is_tracked ?? false,
            allowOversell: $variant->inventoryItem?->allow_oversell ?? false,
        );
    }

    /**
     * @param  array<int, int>  $inventoryByLocationId
     */
    private function syncInventoryLevels(ProductVariant $variant, array $inventoryByLocationId): void
    {
        foreach ($inventoryByLocationId as $locationId => $quantity) {
            $location = InventoryLocation::query()->find($locationId);
            if ($location === null) {
                throw new CrossStoreAssignmentException("Inventory location #{$locationId} does not exist in the current store.");
            }
            $this->inventory->setAvailable($variant, $location, $quantity);
        }
    }

    /**
     * @param  string[]  $tags
     */
    private function syncTags(Product $product, array $tags): void
    {
        $normalized = collect($tags)
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
            ->unique(fn (string $tag): string => mb_strtolower($tag))
            ->values();

        $product->tags()->delete();
        foreach ($normalized as $tag) {
            $product->tags()->create(['name' => $tag]);
        }
    }

    private function assertOrganizationOwnership(ProductEditorData $data): void
    {
        if ($data->categoryId !== null && Category::query()->find($data->categoryId) === null) {
            throw new CrossStoreAssignmentException("Category #{$data->categoryId} does not exist in the current store.");
        }
        if ($data->brandId !== null && Brand::query()->find($data->brandId) === null) {
            throw new CrossStoreAssignmentException("Brand #{$data->brandId} does not exist in the current store.");
        }
    }

    private function assertSuppliedVariantCodesAreUnique(ProductEditorData $data): void
    {
        $skus = [];
        $barcodes = [];
        foreach ($data->variants as $variant) {
            $sku = $this->nullableText($variant->sku);
            if ($sku !== null) {
                if (isset($skus[$sku])) {
                    throw new InvalidProductVariantDataException("SKU [{$sku}] appears more than once.");
                }
                $skus[$sku] = true;
            }
            $barcode = $this->nullableText($variant->barcode);
            if ($barcode !== null) {
                if (isset($barcodes[$barcode])) {
                    throw new InvalidProductVariantDataException("Barcode [{$barcode}] appears more than once.");
                }
                $barcodes[$barcode] = true;
            }
        }
    }

    private function assertVariantCodeAvailable(ProductVariant $variant, ?string $sku, ?string $barcode): void
    {
        if ($sku !== null && ProductVariant::query()->where('sku', $sku)->whereKeyNot($variant->id)->exists()) {
            throw new InvalidProductVariantDataException("SKU [{$sku}] is already used by another variant.");
        }
        if ($barcode !== null && ProductVariant::query()->where('barcode', $barcode)->whereKeyNot($variant->id)->exists()) {
            throw new InvalidProductVariantDataException("Barcode [{$barcode}] is already used by another variant.");
        }
    }

    private function uniqueSlug(string $value, ?Product $except = null): string
    {
        $base = Str::slug($value);
        if ($base === '') {
            throw new \InvalidArgumentException('Product slug cannot be empty.');
        }

        $slug = $base;
        $suffix = 2;
        while (Product::query()->where('slug', $slug)->when($except !== null, fn ($query) => $query->whereKeyNot($except->id))->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function assertProductBelongsToCurrentStore(Product $product): void
    {
        if ($product->store_id !== $this->currentStore->id()) {
            throw new CrossStoreAssignmentException('Product does not belong to the current store.');
        }
    }

    private function nullableText(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    private function loadProduct(Product $product): Product
    {
        return $product->fresh([
            'category', 'brand', 'tags', 'media', 'options.values',
            'variants.inventoryItem.levels.location',
        ]);
    }
}