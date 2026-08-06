<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\DTOs\Catalog\ProductEditorData;
use Modules\Commerce\DTOs\Catalog\ProductOptionInputData;
use Modules\Commerce\DTOs\Catalog\ProductVariantEditorData;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Enums\Catalog\ProductType;
use Modules\Commerce\Exceptions\Catalog\CrossStoreAssignmentException;
use Modules\Commerce\Exceptions\Catalog\InvalidProductOptionsException;
use Modules\Commerce\Exceptions\Catalog\InvalidProductVariantDataException;
use Modules\Commerce\Http\Presenters\AdminProductPresenter;
use Modules\Commerce\Models\Catalog\Brand;
use Modules\Commerce\Models\Catalog\Category;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Catalog\ProductVariant;
use Modules\Commerce\Models\Inventory\InventoryLocation;
use Modules\Commerce\Services\Catalog\ProductDescriptionSanitizer;
use Modules\Commerce\Services\Catalog\ProductEditor;

class AdminProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_map(fn (ProductStatus $status) => $status->value, ProductStatus::cases()))],
            'category_id' => ['nullable', 'string', 'size:26'],
            'brand_id' => ['nullable', 'string', 'size:26'],
            'product_type' => ['nullable', 'string', 'in:'.implode(',', array_map(fn (ProductType $type) => $type->value, ProductType::cases()))],
            'inventory_status' => ['nullable', 'in:in_stock,low_stock,out_of_stock'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $products = Product::query()
            ->with(['category', 'brand', 'media', 'variants.inventoryItem.levels'])
            ->orderByDesc('updated_at');
        $this->applyFilters($products, $validated);
        $products = $products->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => $products->getCollection()->map(fn (Product $product): array => AdminProductPresenter::summary($product))->values(),
            'meta' => $this->meta($products),
            'summary' => $this->summary(),
        ]);
    }

    public function show(string $ulid): JsonResponse
    {
        $product = Product::query()
            ->with(['category', 'brand', 'tags', 'media', 'options.values', 'variants.inventoryItem.levels.location'])
            ->where('ulid', $ulid)
            ->firstOrFail();

        return response()->json(['data' => AdminProductPresenter::detail($product)]);
    }

    public function store(Request $request, ProductEditor $editor, ProductDescriptionSanitizer $descriptions): JsonResponse
    {
        try {
            $product = $editor->create($this->editorData($request, $descriptions));
        } catch (CrossStoreAssignmentException|InvalidProductOptionsException|InvalidProductVariantDataException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => AdminProductPresenter::detail($product)], 201);
    }

    public function update(Request $request, string $ulid, ProductEditor $editor, ProductDescriptionSanitizer $descriptions): JsonResponse
    {
        $product = Product::query()->where('ulid', $ulid)->firstOrFail();

        try {
            $product = $editor->update($product, $this->editorData($request, $descriptions));
        } catch (CrossStoreAssignmentException|InvalidProductOptionsException|InvalidProductVariantDataException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => AdminProductPresenter::detail($product)]);
    }

    public function duplicate(string $ulid, ProductEditor $editor): JsonResponse
    {
        $product = Product::query()
            ->with(['tags', 'options.values', 'variants.inventoryItem.levels'])
            ->where('ulid', $ulid)
            ->firstOrFail();
        $copy = $editor->create(new ProductEditorData(
            title: "{$product->title} Kopya",
            description: $product->description,
            categoryId: $product->category_id,
            brandId: $product->brand_id,
            productType: $product->product_type,
            status: ProductStatus::Draft,
            vendor: $product->vendor,
            isTaxable: $product->is_taxable,
            requiresShipping: $product->requires_shipping,
            metaTitle: $product->meta_title,
            metaDescription: $product->meta_description,
            packageWidth: $product->package_width,
            packageHeight: $product->package_height,
            packageLength: $product->package_length,
            packageDimensionUnit: $product->package_dimension_unit,
            tags: $product->tags->pluck('name')->all(),
            options: $product->options->map(fn ($option) => new ProductOptionInputData($option->name, $option->values->pluck('value')->all()))->all(),
            variants: $product->variants->map(fn (ProductVariant $variant) => new ProductVariantEditorData(
                title: $variant->title,
                price: $variant->price,
                compareAtPrice: $variant->compare_at_price,
                costPrice: $variant->cost_price,
                weight: $variant->weight,
                weightUnit: $variant->weight_unit,
                requiresShipping: $variant->requires_shipping,
                isTaxable: $variant->is_taxable,
                status: ProductStatus::Draft,
                trackInventory: $variant->inventoryItem?->is_tracked ?? false,
            ))->all(),
        ));

        return response()->json(['data' => AdminProductPresenter::detail($copy)], 201);
    }

    public function bulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:100'],
            'product_ids.*' => ['string', 'size:26'],
            'action' => ['required', 'in:activate,draft,archive,delete,change_category'],
            'category_id' => ['required_if:action,change_category', 'nullable', 'string', 'size:26'],
        ]);
        $products = Product::query()->whereIn('ulid', $validated['product_ids'])->get();
        if ($products->count() !== count(array_unique($validated['product_ids']))) {
            abort(404);
        }

        if ($validated['action'] === 'delete') {
            $products->each->delete();
        } elseif ($validated['action'] === 'change_category') {
            $category = Category::query()->where('ulid', $validated['category_id'])->firstOrFail();
            $products->each(fn (Product $product) => $product->update(['category_id' => $category->id]));
        } else {
            $status = match ($validated['action']) {
                'activate' => ProductStatus::Active,
                'draft' => ProductStatus::Draft,
                'archive' => ProductStatus::Archived,
            };
            $products->each(fn (Product $product) => $product->update([
                'status' => $status,
                'published_at' => $status === ProductStatus::Active ? now() : null,
            ]));
        }

        return response()->json(['data' => ['updated_count' => $products->count()]]);
    }

    private function editorData(Request $request, ProductDescriptionSanitizer $descriptions): ProductEditorData
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:100000'],
            'slug' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'string', 'size:26'],
            'brand_id' => ['nullable', 'string', 'size:26'],
            'product_type' => ['required', 'string', 'in:'.implode(',', array_map(fn (ProductType $type) => $type->value, ProductType::cases()))],
            'status' => ['required', 'string', 'in:'.implode(',', array_map(fn (ProductStatus $status) => $status->value, ProductStatus::cases()))],
            'vendor' => ['nullable', 'string', 'max:255'],
            'is_taxable' => ['required', 'boolean'],
            'requires_shipping' => ['required', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'package' => ['nullable', 'array'],
            'package.width' => ['nullable', 'numeric', 'min:0'],
            'package.height' => ['nullable', 'numeric', 'min:0'],
            'package.length' => ['nullable', 'numeric', 'min:0'],
            'package.dimension_unit' => ['nullable', 'in:cm,in'],
            'tags' => ['nullable', 'array', 'max:50'],
            'tags.*' => ['string', 'max:100'],
            'options' => ['nullable', 'array', 'max:3'],
            'options.*.name' => ['required_with:options', 'string', 'max:100'],
            'options.*.values' => ['required_with:options', 'array', 'min:1', 'max:100'],
            'options.*.values.*' => ['string', 'max:100'],
            'variants' => ['nullable', 'array', 'max:500'],
            'variants.*.title' => ['required_with:variants', 'string', 'max:255'],
            'variants.*.price' => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.sku' => ['nullable', 'string', 'max:255'],
            'variants.*.barcode' => ['nullable', 'string', 'max:255'],
            'variants.*.weight' => ['nullable', 'numeric', 'min:0'],
            'variants.*.weight_unit' => ['nullable', 'in:g,kg'],
            'variants.*.requires_shipping' => ['nullable', 'boolean'],
            'variants.*.is_taxable' => ['nullable', 'boolean'],
            'variants.*.status' => ['nullable', 'in:'.implode(',', array_map(fn (ProductStatus $status) => $status->value, ProductStatus::cases()))],
            'variants.*.track_inventory' => ['nullable', 'boolean'],
            'variants.*.allow_oversell' => ['nullable', 'boolean'],
            'variants.*.inventory' => ['nullable', 'array'],
            'variants.*.inventory.*.location_id' => ['required_with:variants.*.inventory', 'string', 'size:26'],
            'variants.*.inventory.*.available_quantity' => ['required_with:variants.*.inventory', 'integer', 'min:0'],
        ]);

        return new ProductEditorData(
            title: $validated['title'],
            description: $descriptions->sanitize($validated['description'] ?? null),
            slug: $validated['slug'] ?? null,
            categoryId: $this->idFor(Category::class, $validated['category_id'] ?? null),
            brandId: $this->idFor(Brand::class, $validated['brand_id'] ?? null),
            productType: ProductType::from($validated['product_type']),
            status: ProductStatus::from($validated['status']),
            vendor: $validated['vendor'] ?? null,
            isTaxable: $validated['is_taxable'],
            requiresShipping: $validated['requires_shipping'],
            metaTitle: $validated['meta_title'] ?? null,
            metaDescription: $validated['meta_description'] ?? null,
            packageWidth: isset($validated['package']['width']) ? (string) $validated['package']['width'] : null,
            packageHeight: isset($validated['package']['height']) ? (string) $validated['package']['height'] : null,
            packageLength: isset($validated['package']['length']) ? (string) $validated['package']['length'] : null,
            packageDimensionUnit: $validated['package']['dimension_unit'] ?? 'cm',
            tags: $validated['tags'] ?? [],
            options: collect($validated['options'] ?? [])->map(fn (array $option) => new ProductOptionInputData(
                name: $option['name'],
                values: $option['values'],
            ))->all(),
            variants: collect($validated['variants'] ?? [])->map(fn (array $variant) => new ProductVariantEditorData(
                title: $variant['title'],
                price: (string) $variant['price'],
                compareAtPrice: isset($variant['compare_at_price']) ? (string) $variant['compare_at_price'] : null,
                costPrice: isset($variant['cost_price']) ? (string) $variant['cost_price'] : null,
                sku: $variant['sku'] ?? null,
                barcode: $variant['barcode'] ?? null,
                weight: isset($variant['weight']) ? (string) $variant['weight'] : null,
                weightUnit: $variant['weight_unit'] ?? 'kg',
                requiresShipping: $variant['requires_shipping'] ?? $validated['requires_shipping'],
                isTaxable: $variant['is_taxable'] ?? $validated['is_taxable'],
                status: ProductStatus::from($variant['status'] ?? $validated['status']),
                trackInventory: $variant['track_inventory'] ?? false,
                allowOversell: $variant['allow_oversell'] ?? false,
                inventoryByLocationId: $this->inventoryMap($variant['inventory'] ?? []),
            ))->all(),
        );
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function idFor(string $model, ?string $ulid): ?int
    {
        if ($ulid === null) {
            return null;
        }

        return $model::query()->where('ulid', $ulid)->value('id')
            ?? throw new CrossStoreAssignmentException('Referenced catalog resource does not belong to the current store.');
    }

    /**
     * @param  array<int, array{location_id: string, available_quantity: int}>  $inventory
     * @return array<int, int>
     */
    private function inventoryMap(array $inventory): array
    {
        $result = [];
        foreach ($inventory as $entry) {
            $locationId = InventoryLocation::query()->where('ulid', $entry['location_id'])->value('id');
            if ($locationId === null) {
                throw new CrossStoreAssignmentException('Inventory location does not belong to the current store.');
            }
            $result[$locationId] = $entry['available_quantity'];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (($search = trim((string) ($filters['q'] ?? ''))) !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $query) use ($like): void {
                $query
                    ->whereRaw('LOWER(title) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(vendor) LIKE ?', [$like])
                    ->orWhereHas('variants', fn (Builder $variants) => $variants
                        ->whereRaw('LOWER(sku) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(barcode) LIKE ?', [$like]))
                    ->orWhereHas('category', fn (Builder $category) => $category->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('brand', fn (Builder $brand) => $brand->whereRaw('LOWER(name) LIKE ?', [$like]));
            });
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['category_id'])) {
            $query->whereHas('category', fn (Builder $category) => $category->where('ulid', $filters['category_id']));
        }
        if (isset($filters['brand_id'])) {
            $query->whereHas('brand', fn (Builder $brand) => $brand->where('ulid', $filters['brand_id']));
        }
        if (isset($filters['product_type'])) {
            $query->where('product_type', $filters['product_type']);
        }
        if (isset($filters['inventory_status'])) {
            $expression = $this->inventoryExpression();
            match ($filters['inventory_status']) {
                'out_of_stock' => $query->whereRaw("{$expression} <= 0"),
                'low_stock' => $query->whereRaw("{$expression} BETWEEN 1 AND 5"),
                'in_stock' => $query->whereRaw("{$expression} > 5"),
            };
        }
    }

    /**
     * @return array<string, int>
     */
    private function summary(): array
    {
        $products = Product::query();
        $inventory = $this->inventoryExpression();

        return [
            'all' => (clone $products)->count(),
            'active' => (clone $products)->where('status', ProductStatus::Active->value)->count(),
            'draft' => (clone $products)->where('status', ProductStatus::Draft->value)->count(),
            'archived' => (clone $products)->where('status', ProductStatus::Archived->value)->count(),
            'out_of_stock' => (clone $products)->whereRaw("{$inventory} <= 0")->count(),
            'low_stock' => (clone $products)->whereRaw("{$inventory} BETWEEN 1 AND 5")->count(),
        ];
    }

    private function inventoryExpression(): string
    {
        return '(SELECT COALESCE(SUM(GREATEST(il.available_quantity - il.reserved_quantity, 0)), 0) FROM product_variants pv JOIN inventory_items ii ON ii.product_variant_id = pv.id AND ii.store_id = products.store_id JOIN inventory_levels il ON il.inventory_item_id = ii.id AND il.store_id = products.store_id WHERE pv.product_id = products.id AND pv.store_id = products.store_id AND pv.deleted_at IS NULL AND ii.is_tracked = true)';
    }

    /**
     * @return array<string, int>
     */
    private function meta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}