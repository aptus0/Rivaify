<?php

namespace Modules\Commerce\Http\Controllers\Storefront;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Models\Catalog\Product;

class StorefrontCatalogController extends Controller
{
    public function store(CurrentStore $currentStore): JsonResponse
    {
        $store = $currentStore->store();

        return response()->json(['data' => [
            'name' => $store->name,
            'slug' => $store->slug,
            'currency' => $store->default_currency,
            'locale' => $store->default_locale,
        ]]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);
        $products = Product::query()
            ->with(['variants' => fn ($query) => $query->where('status', ProductStatus::Active->value)])
            ->where('status', ProductStatus::Active->value)
            ->orderBy('title')
            ->paginate($validated['per_page'] ?? 24);

        return response()->json([
            'data' => $products->getCollection()->map(fn (Product $product): array => $this->present($product))->values(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::query()
            ->with(['variants' => fn ($query) => $query->where('status', ProductStatus::Active->value), 'options.values'])
            ->where('slug', $slug)
            ->where('status', ProductStatus::Active->value)
            ->firstOrFail();

        return response()->json(['data' => $this->present($product, true)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Product $product, bool $includeOptions = false): array
    {
        return [
            'id' => $product->ulid,
            'title' => $product->title,
            'slug' => $product->slug,
            'description' => $product->description,
            'is_taxable' => $product->is_taxable,
            'requires_shipping' => $product->requires_shipping,
            'variants' => $product->variants->map(fn ($variant): array => [
                'id' => $variant->ulid,
                'title' => $variant->title,
                'sku' => $variant->sku,
                'price' => $variant->price,
                'compare_at_price' => $variant->compare_at_price,
            ])->values()->all(),
            'options' => ! $includeOptions ? null : $product->options->map(fn ($option): array => [
                'name' => $option->name,
                'values' => $option->values->pluck('value')->values()->all(),
            ])->values()->all(),
        ];
    }
}