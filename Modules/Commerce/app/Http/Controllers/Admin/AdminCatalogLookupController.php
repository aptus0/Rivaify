<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Actions\Catalog\CreateBrand;
use Modules\Commerce\Actions\Catalog\CreateCategory;
use Modules\Commerce\DTOs\Catalog\CreateBrandData;
use Modules\Commerce\DTOs\Catalog\CreateCategoryData;
use Modules\Commerce\Enums\Catalog\BrandStatus;
use Modules\Commerce\Enums\Catalog\CategoryStatus;
use Modules\Commerce\Models\Catalog\Brand;
use Modules\Commerce\Models\Catalog\Category;
use Modules\Commerce\Models\Inventory\InventoryLocation;

class AdminCatalogLookupController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            'categories' => Category::query()->with('parent')->orderBy('name')->get()->map(fn (Category $category): array => $this->category($category))->values(),
            'brands' => Brand::query()->orderBy('name')->get()->map(fn (Brand $brand): array => $this->brand($brand))->values(),
            'locations' => InventoryLocation::query()->where('is_active', true)->orderBy('name')->get()->map(fn (InventoryLocation $location): array => [
                'id' => $location->ulid,
                'name' => $location->name,
                'code' => $location->code,
            ])->values(),
        ]]);
    }

    public function storeCategory(Request $request, CreateCategory $categories): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'string', 'size:26'],
        ]);
        $parentId = $validated['parent_id'] === null ? null : Category::query()->where('ulid', $validated['parent_id'])->value('id');
        if ($validated['parent_id'] !== null && $parentId === null) {
            abort(422, 'parent_category_not_found');
        }
        $category = $categories->handle(new CreateCategoryData(name: $validated['name'], parentId: $parentId));
        $category->update(['status' => CategoryStatus::Active]);

        return response()->json(['data' => $this->category($category->fresh('parent'))], 201);
    }

    public function storeBrand(Request $request, CreateBrand $brands): JsonResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $brand = $brands->handle(new CreateBrandData(name: $validated['name']));
        $brand->update(['status' => BrandStatus::Active]);

        return response()->json(['data' => $this->brand($brand->fresh())], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function category(Category $category): array
    {
        return [
            'id' => $category->ulid,
            'name' => $category->name,
            'parent_id' => $category->parent?->ulid,
            'status' => $category->status->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function brand(Brand $brand): array
    {
        return ['id' => $brand->ulid, 'name' => $brand->name, 'status' => $brand->status->value];
    }
}