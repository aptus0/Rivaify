<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Enums\Catalog\CollectionStatus;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Catalog\ProductCollection;

class AdminCollectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:'.$this->statuses()],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $collections = ProductCollection::query()->withCount('products')->orderBy('position')->orderBy('name');
        $search = trim((string) ($validated['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $collections->where(function (Builder $query) use ($like): void {
                $query->whereRaw('LOWER(name) LIKE ?', [$like])->orWhereRaw('LOWER(slug) LIKE ?', [$like]);
            });
        }
        if (isset($validated['status'])) {
            $collections->where('status', $validated['status']);
        }
        $collections = $collections->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => $collections->getCollection()->map(fn (ProductCollection $collection): array => $this->present($collection))->values(),
            'meta' => $this->meta($collections),
        ]);
    }

    public function show(string $ulid): JsonResponse
    {
        $collection = $this->detailQuery()->where('ulid', $ulid)->firstOrFail();

        return response()->json(['data' => $this->present($collection)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $collection = DB::transaction(function () use ($validated): ProductCollection {
            $collection = ProductCollection::query()->create([
                'name' => trim($validated['name']),
                'slug' => $this->uniqueSlug((string) ($validated['slug'] ?? $validated['name'])),
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? CollectionStatus::Draft->value,
                'position' => $validated['position'] ?? 0,
            ]);
            $this->syncProductIds($collection, $validated['product_ids'] ?? []);

            return $collection;
        });

        return response()->json(['data' => $this->present($this->reload($collection))], 201);
    }

    public function update(Request $request, string $ulid): JsonResponse
    {
        $collection = ProductCollection::query()->where('ulid', $ulid)->firstOrFail();
        $validated = $this->validatePayload($request, false);
        DB::transaction(function () use ($collection, $validated): void {
            $attributes = [];
            foreach (['name', 'description', 'status', 'position'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $attributes[$field] = $field === 'name' ? trim($validated[$field]) : $validated[$field];
                }
            }
            if (array_key_exists('slug', $validated)) {
                $source = trim((string) ($validated['slug'] ?? ''));
                $attributes['slug'] = $this->uniqueSlug($source !== '' ? $source : ($attributes['name'] ?? $collection->name), $collection);
            }
            $collection->update($attributes);
            if (array_key_exists('product_ids', $validated)) {
                $this->syncProductIds($collection, $validated['product_ids']);
            }
        });

        return response()->json(['data' => $this->present($this->reload($collection))]);
    }

    public function syncProducts(Request $request, string $ulid): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'max:500'],
            'product_ids.*' => ['required', 'string', 'size:26', 'distinct'],
        ]);
        $collection = ProductCollection::query()->where('ulid', $ulid)->firstOrFail();
        DB::transaction(fn () => $this->syncProductIds($collection, $validated['product_ids']));

        return response()->json(['data' => $this->present($this->reload($collection))]);
    }

    public function destroy(string $ulid): JsonResponse
    {
        $collection = ProductCollection::query()->where('ulid', $ulid)->firstOrFail();
        $collection->delete();

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
            'status' => ['sometimes', 'string', 'in:'.$this->statuses()],
            'position' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'product_ids' => ['sometimes', 'array', 'max:500'],
            'product_ids.*' => ['required', 'string', 'size:26', 'distinct'],
        ]);
    }

    /** @param array<int, string> $ulids */
    private function syncProductIds(ProductCollection $collection, array $ulids): void
    {
        $products = Product::query()->whereIn('ulid', $ulids)->get(['id', 'ulid']);
        if ($products->count() !== count($ulids)) {
            throw ValidationException::withMessages(['product_ids' => ['Seçilen ürünlerden en az biri bu mağazada bulunamadı.']]);
        }
        $byUlid = $products->keyBy('ulid');
        $sync = [];
        foreach (array_values($ulids) as $position => $ulid) {
            $sync[$byUlid[$ulid]->id] = ['store_id' => $collection->store_id, 'position' => $position];
        }
        $collection->products()->sync($sync);
    }

    private function uniqueSlug(string $value, ?ProductCollection $except = null): string
    {
        $base = Str::slug($value) ?: 'koleksiyon';
        $slug = $base;
        $suffix = 2;
        while (ProductCollection::query()->when($except, fn (Builder $query) => $query->whereKeyNot($except->id))->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function detailQuery(): Builder
    {
        return ProductCollection::query()->withCount('products')->with(['products.media']);
    }

    private function reload(ProductCollection $collection): ProductCollection
    {
        return $this->detailQuery()->findOrFail($collection->id);
    }

    /** @return array<string, mixed> */
    private function present(ProductCollection $collection): array
    {
        $payload = [
            'id' => $collection->ulid,
            'name' => $collection->name,
            'slug' => $collection->slug,
            'description' => $collection->description,
            'status' => $collection->status->value,
            'position' => $collection->position,
            'product_count' => (int) ($collection->products_count ?? 0),
            'updated_at' => $collection->updated_at?->toIso8601String(),
        ];
        if ($collection->relationLoaded('products')) {
            $payload['products'] = $collection->products->map(function (Product $product): array {
                $media = $product->media->firstWhere('is_featured', true) ?? $product->media->first();

                return [
                    'id' => $product->ulid,
                    'title' => $product->title,
                    'slug' => $product->slug,
                    'status' => $product->status->value,
                    'featured_media_url' => $media === null ? null : "/api/v1/products/{$product->ulid}/media/{$media->ulid}/file",
                    'position' => (int) $product->pivot->position,
                ];
            })->values();
        }

        return $payload;
    }

    private function statuses(): string
    {
        return implode(',', array_map(fn (CollectionStatus $status): string => $status->value, CollectionStatus::cases()));
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

