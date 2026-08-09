<?php

namespace Modules\Commerce\Services\Storefront;

use App\Core\Tenancy\CurrentStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Models\Storefront\BuilderDocument;
use Modules\Commerce\Models\Storefront\StoreTheme;
use Modules\Commerce\Models\Storefront\StorefrontPublication;
use Modules\Commerce\Models\Storefront\Theme;
use Modules\Commerce\Models\Storefront\ThemeVersion;
use Modules\Store\Models\Store;

class StorefrontBuilderService
{
    private const ALLOWED_SECTIONS = [
        'header', 'announcement', 'hero', 'product-grid', 'product-carousel', 'featured-product',
        'category-showcase', 'collection-grid', 'collection-carousel', 'image-banner', 'image-with-text', 'video',
        'rich-text', 'reviews', 'faq', 'countdown', 'promotion-tiles', 'newsletter', 'instagram-feed',
        'logo-list', 'trust-badges', 'spacer', 'footer',
    ];

    public function __construct(private readonly CurrentStore $currentStore) {}

    public function ensureDefaultTheme(): StoreTheme
    {
        $store = $this->currentStore->store();

        return DB::transaction(function () use ($store): StoreTheme {
            $theme = Theme::query()->firstOrCreate(
                ['key' => 'nova'],
                [
                    'name' => 'Nova',
                    'version' => '2.1',
                    'category' => 'fashion',
                    'defaults' => ['tokens' => self::defaultTokens()],
                ],
            );

            /** @var StoreTheme|null $storeTheme */
            $storeTheme = StoreTheme::query()->where('name', 'Nova v2.1')->first();
            if ($storeTheme === null) {
                $storeTheme = StoreTheme::query()->create([
                    'theme_id' => $theme->id,
                    'name' => 'Nova v2.1',
                    'status' => 'live',
                    'tokens' => self::defaultTokens(),
                    'published_at' => null,
                ]);
            }

            $document = BuilderDocument::query()->firstOrCreate(
                ['store_theme_id' => $storeTheme->id, 'resource_type' => 'home', 'resource_key' => 'home'],
                [
                    'schema_version' => 1,
                    'document' => self::defaultHomeDocument($store),
                    'revision' => 1,
                ],
            );

            if ($this->shouldUpgradeDefaultNovaDocument($document)) {
                $document->forceFill([
                    'document' => self::defaultHomeDocument($store),
                    'revision' => $document->revision + 1,
                ])->save();
            }

            if ($storeTheme->published_version_id === null) {
                $version = ThemeVersion::query()->create([
                    'store_id' => $storeTheme->store_id,
                    'store_theme_id' => $storeTheme->id,
                    'version' => 1,
                    'status' => 'published',
                    'snapshot' => $this->defaultSnapshot($store, $theme, $storeTheme, $document),
                    'published_at' => now(),
                ]);
                $manifest = $this->compileManifest($version);
                StorefrontPublication::query()->create([
                    'store_id' => $storeTheme->store_id,
                    'store_theme_id' => $storeTheme->id,
                    'theme_version_id' => $version->id,
                    'manifest' => $manifest,
                    'published_at' => now(),
                ]);
                $storeTheme->forceFill([
                    'status' => 'live',
                    'published_version_id' => $version->id,
                    'published_at' => now(),
                ])->save();
                Cache::put("storefront:{$store->ulid}:published", $manifest, now()->addHours(12));
            }

            if ($this->shouldUpgradePublishedNovaVersion($storeTheme)) {
                $version = ThemeVersion::query()->create([
                    'store_id' => $storeTheme->store_id,
                    'store_theme_id' => $storeTheme->id,
                    'version' => ((int) ThemeVersion::query()->where('store_theme_id', $storeTheme->id)->max('version')) + 1,
                    'status' => 'published',
                    'snapshot' => $this->defaultSnapshot($store, $theme, $storeTheme, $document),
                    'published_at' => now(),
                ]);
                $manifest = $this->compileManifest($version);
                StorefrontPublication::query()->create([
                    'store_id' => $storeTheme->store_id,
                    'store_theme_id' => $storeTheme->id,
                    'theme_version_id' => $version->id,
                    'manifest' => $manifest,
                    'published_at' => now(),
                ]);
                $storeTheme->forceFill([
                    'status' => 'live',
                    'published_version_id' => $version->id,
                    'published_at' => now(),
                ])->save();
                Cache::put("storefront:{$store->ulid}:published", $manifest, now()->addHours(12));
            }

            return $storeTheme->load(['baseTheme', 'documents', 'publishedVersion']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $store = $this->currentStore->store()->load(['domains' => fn ($query) => $query->orderByDesc('is_primary')]);
        $theme = $this->ensureDefaultTheme();
        $document = $theme->documents->firstWhere('resource_type', 'home');
        $primaryDomain = $store->domains->firstWhere('is_primary', true) ?? $store->domains->first();
        $lastPublish = $theme->published_at;

        return [
            'store' => [
                'id' => $store->ulid,
                'name' => $store->name,
                'slug' => $store->slug,
                'status' => $theme->published_version_id === null ? 'draft' : 'live',
                'domain' => $primaryDomain?->domain ?? "{$store->slug}.rivaify.com",
                'storefront_url' => 'https://'.($primaryDomain?->domain ?? "{$store->slug}.rivaify.com"),
            ],
            'summary' => [
                'theme' => $theme->name,
                'domain_status' => $primaryDomain?->verified_at ? 'connected' : 'pending',
                'last_publish' => $lastPublish?->toIso8601String(),
                'pages' => 14,
                'menus' => 2,
            ],
            'theme' => $this->presentTheme($theme),
            'document' => $document ? $this->presentDocument($document) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function themeLibrary(): array
    {
        $this->ensureDefaultTheme();

        $themes = StoreTheme::query()
            ->with(['baseTheme', 'publishedVersion', 'release.publisher'])
            ->latest('id')
            ->get()
            ->map(fn (StoreTheme $theme): array => $this->presentTheme($theme))
            ->values()
            ->all();

        return ['themes' => $themes];
    }

    public function findTheme(string $ulid): StoreTheme
    {
        return StoreTheme::query()
            ->with(['baseTheme', 'documents', 'publishedVersion'])
            ->where('ulid', $ulid)
            ->firstOrFail();
    }

    public function updateDocument(BuilderDocument $document, int $revision, array $payload, int $userId): BuilderDocument
    {
        $this->validateDocument($payload, $document->loadMissing('storeTheme.release')->storeTheme);

        return DB::transaction(function () use ($document, $revision, $payload, $userId): BuilderDocument {
            /** @var BuilderDocument $locked */
            $locked = BuilderDocument::query()->lockForUpdate()->whereKey($document->id)->firstOrFail();
            if ($locked->revision !== $revision) {
                abort(response()->json([
                    'message' => 'document_revision_conflict',
                    'current_revision' => $locked->revision,
                ], 409));
            }

            $locked->forceFill([
                'document' => $payload,
                'revision' => $locked->revision + 1,
                'updated_by' => $userId,
            ])->save();

            return $locked->refresh();
        });
    }

    public function publish(StoreTheme $theme, int $userId): ThemeVersion
    {
        return DB::transaction(function () use ($theme, $userId): ThemeVersion {
            $theme = StoreTheme::query()->with('documents')->lockForUpdate()->whereKey($theme->id)->firstOrFail();
            $home = $theme->documents->firstWhere('resource_type', 'home');
            if (! $home) {
                throw ValidationException::withMessages(['document' => 'Ana sayfa dokümanı bulunamadı.']);
            }

            $this->validateDocument($home->document, $theme);
            $this->validateRequiredSections($home->document);

            $nextVersion = ((int) ThemeVersion::query()->where('store_theme_id', $theme->id)->max('version')) + 1;
            $snapshot = [
                'store' => $this->currentStore->store()->ulid,
                'theme' => $theme->baseTheme?->key ?? 'nova',
                'version' => $nextVersion,
                'tokens' => $theme->tokens,
                'templates' => [
                    'home' => $home->document,
                ],
                'navigation' => [
                    'main' => [
                        ['label' => 'Ana Sayfa', 'href' => '/'],
                        ['label' => 'Ürünler', 'href' => '/products'],
                        ['label' => 'İletişim', 'href' => '/pages/iletisim'],
                    ],
                ],
                'published_at' => now()->toIso8601String(),
            ];

            $version = ThemeVersion::query()->create([
                'store_id' => $theme->store_id,
                'store_theme_id' => $theme->id,
                'version' => $nextVersion,
                'status' => 'published',
                'snapshot' => $snapshot,
                'created_by' => $userId,
                'published_at' => now(),
            ]);

            $manifest = $this->compileManifest($version);
            StorefrontPublication::query()->create([
                'store_id' => $theme->store_id,
                'store_theme_id' => $theme->id,
                'theme_version_id' => $version->id,
                'manifest' => $manifest,
                'published_at' => now(),
            ]);

            $theme->forceFill([
                'status' => 'live',
                'published_version_id' => $version->id,
                'published_at' => now(),
            ])->save();

            Cache::put("storefront:{$this->currentStore->store()->ulid}:published", $manifest, now()->addHours(12));

            return $version;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function compileManifest(ThemeVersion $version): array
    {
        return [
            'store' => $this->currentStore->store()->ulid,
            'theme_version_id' => $version->ulid,
            'version' => $version->version,
            ...$version->snapshot,
        ];
    }

    private function shouldUpgradeDefaultNovaDocument(BuilderDocument $document): bool
    {
        $types = collect($document->document['sections'] ?? [])->pluck('type')->all();

        return in_array('hero', $types, true)
            && in_array('product-grid', $types, true)
            && ! in_array('category-showcase', $types, true)
            && ! in_array('promotion-tiles', $types, true);
    }

    private function shouldUpgradePublishedNovaVersion(StoreTheme $theme): bool
    {
        $published = $theme->publishedVersion;
        $sections = $published?->snapshot['templates']['home']['sections'] ?? [];
        $types = collect($sections)->pluck('type')->all();

        return $published !== null
            && in_array('hero', $types, true)
            && in_array('product-grid', $types, true)
            && ! in_array('category-showcase', $types, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultSnapshot(Store $store, Theme $theme, StoreTheme $storeTheme, BuilderDocument $document): array
    {
        return [
            'store' => $store->ulid,
            'theme' => $theme->key,
            'version' => 1,
            'tokens' => $storeTheme->tokens,
            'templates' => ['home' => $document->document],
            'navigation' => [
                'main' => [
                    ['label' => 'Ana Sayfa', 'href' => '/'],
                    ['label' => 'Ürünler', 'href' => '/products'],
                    ['label' => 'Favoriler', 'href' => '/wishlist'],
                    ['label' => 'İletişim', 'href' => '/pages/iletisim'],
                ],
            ],
            'published_at' => now()->toIso8601String(),
        ];
    }

    private function validateDocument(array $document, ?StoreTheme $theme = null): void
    {
        $errors = [];
        $allowedSections = $this->allowedSectionsForTheme($theme);
        if (($document['version'] ?? null) !== 1) {
            $errors['version'] = 'Builder schema version 1 olmalıdır.';
        }
        if (! is_array($document['sections'] ?? null)) {
            $errors['sections'] = 'Sections array olmalıdır.';
        }

        foreach (($document['sections'] ?? []) as $index => $section) {
            $type = is_array($section) ? ($section['type'] ?? null) : null;
            if (! is_string($type) || ! in_array($type, $allowedSections, true)) {
                $errors["sections.{$index}.type"] = 'Geçersiz bölüm tipi.';
            }
            if (! is_string($section['id'] ?? null)) {
                $errors["sections.{$index}.id"] = 'Bölüm ID zorunludur.';
            }
            if (isset($section['settings']) && ! is_array($section['settings'])) {
                $errors["sections.{$index}.settings"] = 'Bölüm ayarları object olmalıdır.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array<int, string>
     */
    private function allowedSectionsForTheme(?StoreTheme $theme): array
    {
        $manifestSections = collect($theme?->release?->manifest['sections'] ?? [])
            ->filter(fn ($section): bool => is_array($section) && is_string($section['type'] ?? null))
            ->pluck('type')
            ->all();

        return array_values(array_unique([...self::ALLOWED_SECTIONS, ...$manifestSections]));
    }

    private function validateRequiredSections(array $document): void
    {
        $types = collect($document['sections'] ?? [])->pluck('type')->all();
        $missing = array_values(array_diff(['header', 'footer'], $types));
        if ($missing !== []) {
            throw ValidationException::withMessages(['sections' => 'Yayın için Header ve Footer bölümleri gereklidir.']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function presentTheme(StoreTheme $theme): array
    {
        return [
            'id' => $theme->ulid,
            'name' => $theme->name,
            'base_key' => $theme->baseTheme?->key ?? 'nova',
            'base_version' => $theme->baseTheme?->version ?? '2.1',
            'release_version' => $theme->release?->version,
            'source' => $theme->source ?? 'official',
            'trust_level' => $theme->trust_level ?? 'official',
            'publisher' => $theme->release?->publisher?->name,
            'engine' => $theme->release?->engine_constraint,
            'status' => $theme->published_version_id ? 'live' : $theme->status,
            'tokens' => $theme->tokens,
            'published_at' => $theme->published_at?->toIso8601String(),
            'published_version' => $theme->publishedVersion?->version,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentDocument(BuilderDocument $document): array
    {
        return [
            'id' => $document->ulid,
            'resource_type' => $document->resource_type,
            'resource_key' => $document->resource_key,
            'schema_version' => $document->schema_version,
            'revision' => $document->revision,
            'document' => $document->document,
            'updated_at' => $document->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultTokens(): array
    {
        return [
            'colors' => [
                'primary' => '#111111',
                'secondary' => '#F3F4F6',
                'background' => '#FFFFFF',
                'surface' => '#FFFFFF',
                'text' => '#101010',
                'muted' => '#6B7280',
                'brand' => '#0F766E',
            ],
            'typography' => [
                'heading_font' => 'Manrope',
                'body_font' => 'Inter',
                'heading_scale' => 'normal',
                'body_scale' => 'normal',
            ],
            'radius' => [
                'button' => '8px',
                'card' => '8px',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultHomeDocument(Store $store): array
    {
        return [
            'version' => 1,
            'template' => 'home',
            'sections' => [
                ['id' => 'sec_announcement_01', 'type' => 'announcement', 'enabled' => true, 'settings' => ['text' => '1.500 TL üzeri ücretsiz kargo · Kolay iade · Güvenli ödeme', 'scheme' => 'dark']],
                ['id' => 'sec_header_01', 'type' => 'header', 'enabled' => true, 'locked' => true, 'settings' => ['preset' => 'search-focused', 'sticky' => true, 'show_search' => true, 'show_wishlist' => true, 'show_account' => true, 'show_cart' => true, 'height' => 'standard'], 'blocks' => [['id' => 'blk_logo_01', 'type' => 'logo', 'settings' => ['text' => $store->name]], ['id' => 'blk_nav_01', 'type' => 'navigation', 'settings' => ['menu' => 'main']]]],
                ['id' => 'sec_hero_01', 'type' => 'hero', 'enabled' => true, 'settings' => ['eyebrow' => 'Rivaify Nova', 'title' => 'Mağazanız bugün premium görünsün', 'subtitle' => 'Profesyonel vitrin, hızlı alışveriş deneyimi ve mobil uyumlu commerce tasarımı.', 'button_label' => 'Ürünleri Keşfet', 'alignment' => 'left', 'height' => 'large', 'scheme' => 'dark', 'overlay' => 10, 'visibility' => ['desktop' => true, 'tablet' => true, 'mobile' => true]]],
                ['id' => 'sec_categories_01', 'type' => 'category-showcase', 'enabled' => true, 'settings' => ['title' => 'Kategorileri keşfet', 'layout' => 'icon-grid']],
                ['id' => 'sec_products_new_01', 'type' => 'product-grid', 'enabled' => true, 'settings' => ['title' => 'Yeni Gelenler', 'description' => 'Aktif ürünleriniz otomatik olarak bu alanda premium kartlarla görünür.', 'source' => ['type' => 'latest_products', 'label' => 'Yeni Gelenler'], 'limit' => 8, 'columns' => ['desktop' => 4, 'tablet' => 3, 'mobile' => 2], 'card_preset' => 'commerce']],
                ['id' => 'sec_banner_01', 'type' => 'image-with-text', 'enabled' => true, 'settings' => ['title' => 'Günlük stile premium dokunuş', 'body' => 'Rivaify Nova ile düzenlediğiniz tasarım, müşterinin gördüğü aynı renderer üzerinden yayınlanır.', 'image_position' => 'left']],
                ['id' => 'sec_products_best_01', 'type' => 'product-carousel', 'enabled' => true, 'settings' => ['title' => 'Çok Satanlar', 'description' => 'Koleksiyon veya son ürün kaynağıyla dinamik ürün alanı.', 'source' => ['type' => 'latest_products', 'label' => 'Çok Satanlar'], 'limit' => 4, 'columns' => ['desktop' => 4, 'tablet' => 3, 'mobile' => 2], 'card_preset' => 'commerce']],
                ['id' => 'sec_promo_01', 'type' => 'promotion-tiles', 'enabled' => true, 'settings' => ['title' => 'Sezon kampanyasını öne çıkar', 'body' => 'Kampanya, koleksiyon veya marka hikayeniz için güçlü görsel alan.', 'image_position' => 'right']],
                ['id' => 'sec_trust_01', 'type' => 'trust-badges', 'enabled' => true, 'settings' => ['title' => 'Güven veren alışveriş']],
                ['id' => 'sec_newsletter_01', 'type' => 'newsletter', 'enabled' => true, 'settings' => ['title' => 'Yeni koleksiyonları kaçırma', 'description' => 'Kampanya ve yeni ürün haberlerini ilk öğrenenlerden olun.', 'button_label' => 'Kaydol']],
                ['id' => 'sec_footer_01', 'type' => 'footer', 'enabled' => true, 'locked' => true, 'settings' => ['preset' => 'large-commerce', 'show_newsletter' => true, 'show_social' => true, 'show_payments' => true]],
            ],
        ];
    }
}
