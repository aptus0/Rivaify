<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Actions\Catalog\CreateCategory;
use Modules\Commerce\DTOs\Catalog\CreateCategoryData;
use Modules\Commerce\Enums\Catalog\CategoryStatus;
use Modules\Commerce\Models\Catalog\Category;

class AdminCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:'.$this->statuses()],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $categories = Category::query()
            ->with('parent')
            ->withCount(['products', 'children'])
            ->orderBy('position')
            ->orderBy('name');

        $search = trim((string) ($validated['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $categories->where(function (Builder $query) use ($like): void {
                $query->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(slug) LIKE ?', [$like]);
            });
        }
        if (isset($validated['status'])) {
            $categories->where('status', $validated['status']);
        }

        $categories = $categories->paginate($validated['per_page'] ?? 50);

        return response()->json([
            'data' => $categories->getCollection()->map(fn (Category $category): array => $this->present($category))->values(),
            'meta' => $this->meta($categories),
        ]);
    }

    public function show(string $ulid): JsonResponse
    {
        $category = Category::query()->with('parent')->withCount(['products', 'children'])->where('ulid', $ulid)->firstOrFail();

        return response()->json(['data' => $this->present($category)]);
    }

    public function store(Request $request, CreateCategory $categories): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $parentId = $this->resolveParent($validated['parent_id'] ?? null);
        $category = $categories->handle(new CreateCategoryData(
            name: trim($validated['name']),
            description: $validated['description'] ?? null,
            parentId: $parentId,
            position: $validated['position'] ?? 0,
        ));
        $category->update([
            'slug' => $this->uniqueSlug((string) ($validated['slug'] ?? $category->slug), $category),
            'status' => $validated['status'] ?? CategoryStatus::Active->value,
        ]);

        return response()->json(['data' => $this->present($this->reload($category))], 201);
    }

    public function update(Request $request, string $ulid): JsonResponse
    {
        $category = Category::query()->where('ulid', $ulid)->firstOrFail();
        $validated = $this->validatePayload($request, false);
        $attributes = [];
        foreach (['name', 'description', 'position', 'status'] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = $field === 'name' ? trim($validated[$field]) : $validated[$field];
            }
        }
        if (array_key_exists('parent_id', $validated)) {
            $attributes['parent_id'] = $this->resolveParent($validated['parent_id'], $category);
        }
        if (array_key_exists('slug', $validated)) {
            $slugSource = trim((string) ($validated['slug'] ?? ''));
            $attributes['slug'] = $this->uniqueSlug($slugSource !== '' ? $slugSource : ($attributes['name'] ?? $category->name), $category);
        }
        $category->update($attributes);

        return response()->json(['data' => $this->present($this->reload($category))]);
    }

    public function destroy(string $ulid): JsonResponse
    {
        $category = Category::query()->where('ulid', $ulid)->firstOrFail();
        $category->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function validatePayload(Request $request, bool $creating = true): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'parent_id' => ['sometimes', 'nullable', 'string', 'size:26'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'status' => ['sometimes', 'string', 'in:'.$this->statuses()],
        ]);
    }

    private function resolveParent(?string $parentUlid, ?Category $category = null): ?int
    {
        if ($parentUlid === null || $parentUlid === '') {
            return null;
        }
        $parent = Category::query()->where('ulid', $parentUlid)->first();
        if ($parent === null) {
            throw ValidationException::withMessages(['parent_id' => ['Üst kategori bu mağazada bulunamadı.']]);
        }

        $cursor = $parent;
        while ($category !== null && $cursor !== null) {
            if ($cursor->id === $category->id) {
                throw ValidationException::withMessages(['parent_id' => ['Kategori kendisinin veya alt kategorisinin altına taşınamaz.']]);
            }
            $cursor = $cursor->parent_id === null ? null : Category::query()->find($cursor->parent_id);
        }

        return $parent->id;
    }

    private function uniqueSlug(string $value, ?Category $except = null): string
    {
        $base = Str::slug($value) ?: 'kategori';
        $slug = $base;
        $suffix = 2;
        while (Category::query()->when($except, fn (Builder $query) => $query->whereKeyNot($except->id))->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function reload(Category $category): Category
    {
        return $category->fresh(['parent'])->loadCount(['products', 'children']);
    }

    /** @return array<string, mixed> */
    private function present(Category $category): array
    {
        return [
            'id' => $category->ulid,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'status' => $category->status->value,
            'position' => $category->position,
            'parent' => $category->parent === null ? null : ['id' => $category->parent->ulid, 'name' => $category->parent->name],
            'product_count' => (int) ($category->products_count ?? 0),
            'children_count' => (int) ($category->children_count ?? 0),
            'updated_at' => $category->updated_at?->toIso8601String(),
        ];
    }

    private function statuses(): string
    {
        return implode(',', array_map(fn (CategoryStatus $status): string => $status->value, CategoryStatus::cases()));
    }

    /** @return array<string, int> */
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

