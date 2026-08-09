<?php

namespace Modules\Commerce\Http\Controllers\Storefront;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Marketing\MarketingCampaign;
use Modules\Commerce\Services\Storefront\StorefrontBuilderService;
use Modules\Commerce\Services\Storefront\StorefrontCatalogService;
use Symfony\Component\HttpFoundation\Response;

class StorefrontCatalogController extends Controller
{
    public function runtime(CurrentStore $currentStore, StorefrontBuilderService $builder, StorefrontCatalogService $catalog): JsonResponse
    {
        $store = $currentStore->store();
        $theme = $builder->ensureDefaultTheme();
        $snapshot = $theme->publishedVersion?->snapshot;
        $document = is_array($snapshot) && isset($snapshot['templates']['home']) && is_array($snapshot['templates']['home'])
            ? $snapshot['templates']['home']
            : ($theme->documents->firstWhere('resource_type', 'home')?->document ?? StorefrontBuilderService::defaultHomeDocument($store));

        return response()->json(['data' => [
            'mode' => 'published',
            'store' => $this->storePayload($currentStore),
            'theme' => $builder->presentTheme($theme),
            'document' => $document,
            'products' => $catalog->productsForSource(['type' => 'latest_products'], 24),
        ]]);
    }

    public function store(CurrentStore $currentStore): JsonResponse
    {
        return response()->json(['data' => $this->storePayload($currentStore)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function storePayload(CurrentStore $currentStore): array
    {
        $store = $currentStore->store();

        return [
            'name' => $store->name,
            'slug' => $store->slug,
            'currency' => $store->default_currency,
            'locale' => $store->default_locale,
            'announcements' => MarketingCampaign::query()
                ->where('channel', 'online_store')
                ->where('status', 'active')
                ->where(function ($query): void {
                    $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                })
                ->where(function ($query): void {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                })
                ->oldest('created_at')
                ->limit(3)
                ->get()
                ->map(fn (MarketingCampaign $campaign): array => [
                    'id' => $campaign->ulid,
                    'message' => trim((string) ($campaign->content['message'] ?? '')),
                    'ends_at' => $campaign->ends_at?->toIso8601String(),
                ])
                ->filter(fn (array $announcement): bool => $announcement['message'] !== '')
                ->values(),
        ];
    }

    public function index(Request $request, StorefrontCatalogService $catalog): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);
        $products = collect($catalog->productsForSource(['type' => 'latest_products'], $validated['per_page'] ?? 24));

        return response()->json([
            'data' => $products->values(),
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $validated['per_page'] ?? 24,
                'total' => $products->count(),
            ],
        ]);
    }

    public function show(string $slug, StorefrontCatalogService $catalog): JsonResponse
    {
        $product = $catalog->productBySlug($slug);

        return response()->json(['data' => $catalog->present($product, true)]);
    }

    public function media(string $slug, string $mediaUlid): Response
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->where('status', ProductStatus::Active->value)
            ->firstOrFail();
        $media = $product->media()->where('ulid', $mediaUlid)->firstOrFail();
        $disk = Storage::disk($media->storage_disk);
        if (! $disk->exists($media->storage_path)) {
            abort(404);
        }

        return $disk->response($media->storage_path, $media->original_filename, [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

}
