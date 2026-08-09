<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Enums\Catalog\ProductType;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Services\Catalog\ProductCsvManager;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminProductCsvController extends Controller
{
    public function export(Request $request, CurrentStore $currentStore, ProductCsvManager $csv): StreamedResponse
    {
        $filters = $request->validate($this->filterRules());
        $query = Product::query()->with([
            'category',
            'brand',
            'tags',
            'options.values',
            'variants.variantValues.option',
            'variants.variantValues.optionValue',
            'variants.inventoryItem.levels.location',
        ]);
        $this->applyFilters($query, $filters);
        $filename = sprintf('urunler-%s-%s.csv', $currentStore->store()->slug, now()->format('Y-m-d'));

        return response()->streamDownload(
            fn () => $csv->writeExport($query),
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function import(Request $request, ProductCsvManager $csv): JsonResponse
    {
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:2048',
                'extensions:csv',
                'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel,application/octet-stream',
            ],
            'mode' => ['required', 'in:preview,commit'],
        ]);

        $result = $csv->process($validated['file'], $validated['mode'] === 'commit');

        return response()->json(['data' => $result]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function filterRules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_map(fn (ProductStatus $status) => $status->value, ProductStatus::cases()))],
            'category_id' => ['nullable', 'string', 'size:26'],
            'brand_id' => ['nullable', 'string', 'size:26'],
            'product_type' => ['nullable', 'string', 'in:'.implode(',', array_map(fn (ProductType $type) => $type->value, ProductType::cases()))],
            'inventory_status' => ['nullable', 'in:in_stock,low_stock,out_of_stock'],
        ];
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

    private function inventoryExpression(): string
    {
        return '(SELECT COALESCE(SUM(GREATEST(il.available_quantity - il.reserved_quantity, 0)), 0) FROM product_variants pv JOIN inventory_items ii ON ii.product_variant_id = pv.id AND ii.store_id = products.store_id JOIN inventory_levels il ON il.inventory_item_id = ii.id AND il.store_id = products.store_id WHERE pv.product_id = products.id AND pv.store_id = products.store_id AND pv.deleted_at IS NULL AND ii.is_tracked = true)';
    }
}
