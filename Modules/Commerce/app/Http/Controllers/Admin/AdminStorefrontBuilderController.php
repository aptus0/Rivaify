<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Core\Tenancy\CurrentStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Models\Storefront\BuilderDocument;
use Modules\Commerce\Models\Storefront\ThemePackage;
use Modules\Commerce\Services\Storefront\StorefrontBuilderService;
use Modules\Commerce\Services\Storefront\StorefrontCatalogService;
use Modules\Commerce\Services\Storefront\ThemePlatform\RivaThemePackageService;

class AdminStorefrontBuilderController extends Controller
{
    public function __construct(private readonly StorefrontBuilderService $builder) {}

    public function overview(): JsonResponse
    {
        return response()->json(['data' => $this->builder->overview()]);
    }

    public function themes(): JsonResponse
    {
        return response()->json(['data' => $this->builder->themeLibrary()]);
    }

    public function uploadTheme(Request $request, RivaThemePackageService $packages): JsonResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'file', 'max:25600'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $validated['theme'];
        $package = $packages->inspectUpload($file);

        return response()->json(['data' => $packages->presentPackage($package)], $package->status === 'failed' ? 422 : 201);
    }

    public function installThemePackage(string $packageUlid, RivaThemePackageService $packages): JsonResponse
    {
        $package = ThemePackage::query()->where('ulid', $packageUlid)->firstOrFail();
        $theme = $packages->install($package);

        return response()->json(['data' => [
            'theme' => $this->builder->presentTheme($theme),
            'package' => $packages->presentPackage($package->refresh()->load('report')),
        ]]);
    }

    public function editor(string $themeUlid): JsonResponse
    {
        $theme = $this->builder->findTheme($themeUlid);
        $document = $theme->documents->firstWhere('resource_type', 'home')
            ?? $theme->documents()->where('resource_type', 'home')->firstOrFail();

        return response()->json(['data' => [
            'theme' => $this->builder->presentTheme($theme),
            'documents' => [$this->builder->presentDocument($document)],
            'version_history' => $theme->versions()->latest('version')->limit(20)->get()->map(fn ($version): array => [
                'id' => $version->ulid,
                'version' => $version->version,
                'status' => $version->status,
                'published_at' => $version->published_at?->toIso8601String(),
                'created_at' => $version->created_at?->toIso8601String(),
            ])->values(),
        ]]);
    }

    public function previewRuntime(string $themeUlid, StorefrontCatalogService $catalog, CurrentStore $currentStore): JsonResponse
    {
        $theme = $this->builder->findTheme($themeUlid);
        $document = $theme->documents->firstWhere('resource_type', 'home')
            ?? $theme->documents()->where('resource_type', 'home')->firstOrFail();

        return response()->json(['data' => [
            'mode' => 'preview',
            'store' => [
                'name' => $currentStore->store()->name,
                'slug' => $currentStore->store()->slug,
                'currency' => $currentStore->store()->default_currency,
                'locale' => $currentStore->store()->default_locale,
                'announcements' => [],
            ],
            'theme' => $this->builder->presentTheme($theme),
            'document' => $document->document,
            'products' => $catalog->productsForSource(['type' => 'latest_products'], 24),
        ]]);
    }

    public function updateDocument(Request $request, string $documentUlid): JsonResponse
    {
        $validated = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'document' => ['required', 'array'],
        ]);

        $document = BuilderDocument::query()->where('ulid', $documentUlid)->firstOrFail();
        $saved = $this->builder->updateDocument(
            $document,
            (int) $validated['revision'],
            $validated['document'],
            (int) $request->user()->id,
        );

        return response()->json(['data' => [
            ...$this->builder->presentDocument($saved),
            'saved_at' => now()->toIso8601String(),
        ]]);
    }

    public function publish(Request $request, string $themeUlid): JsonResponse
    {
        $theme = $this->builder->findTheme($themeUlid);
        $version = $this->builder->publish($theme, (int) $request->user()->id);

        return response()->json(['data' => [
            'version' => [
                'id' => $version->ulid,
                'number' => $version->version,
                'status' => $version->status,
                'published_at' => $version->published_at?->toIso8601String(),
            ],
            'theme' => $this->builder->presentTheme($theme->refresh()->load(['baseTheme', 'publishedVersion'])),
        ]]);
    }
}
