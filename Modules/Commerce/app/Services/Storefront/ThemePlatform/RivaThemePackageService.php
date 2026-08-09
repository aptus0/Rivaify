<?php

namespace Modules\Commerce\Services\Storefront\ThemePlatform;

use App\Core\Tenancy\CurrentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Models\Storefront\BuilderDocument;
use Modules\Commerce\Models\Storefront\StoreTheme;
use Modules\Commerce\Models\Storefront\Theme;
use Modules\Commerce\Models\Storefront\ThemeCompatibilityReport;
use Modules\Commerce\Models\Storefront\ThemeCompiledArtifact;
use Modules\Commerce\Models\Storefront\ThemePackage;
use Modules\Commerce\Models\Storefront\ThemePublisher;
use Modules\Commerce\Models\Storefront\ThemeRelease;
use Modules\Commerce\Services\Storefront\RivaLang\RivaLangCompiler;
use Modules\Commerce\Services\Storefront\StorefrontBuilderService;
use ZipArchive;

class RivaThemePackageService
{
    private const ENGINE_VERSION = '2.0';
    private const MAX_PACKAGE_BYTES = 25_000_000;
    private const MAX_UNCOMPRESSED_BYTES = 80_000_000;
    private const MAX_FILES = 500;
    private const MAX_FILE_BYTES = 8_000_000;

    private const ALLOWED_EXTENSIONS = [
        'json', 'md', 'txt', 'riva', 'webp', 'png', 'jpg', 'jpeg', 'svg', 'woff', 'woff2', 'css',
    ];

    private const FORBIDDEN_EXTENSIONS = [
        'php', 'phtml', 'phar', 'js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx', 'sh', 'bash', 'zsh',
        'fish', 'bat', 'cmd', 'exe', 'dll', 'so', 'dylib', 'jar', 'py', 'rb', 'go', 'rs',
        'composer', 'lock',
    ];

    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly RivaLangCompiler $compiler,
    ) {}

    public function inspectUpload(UploadedFile $file): ThemePackage
    {
        if (strtolower($file->getClientOriginalExtension()) !== 'rivtheme') {
            throw ValidationException::withMessages(['theme' => 'Sadece .rivtheme paketleri yüklenebilir.']);
        }

        if ($file->getSize() > self::MAX_PACKAGE_BYTES) {
            throw ValidationException::withMessages(['theme' => 'Tema paketi 25 MB sınırını aşıyor.']);
        }

        $store = $this->currentStore->store();
        $hash = hash_file('sha256', $file->getRealPath());
        $path = $file->storeAs("theme-quarantine/{$store->ulid}", "{$hash}.rivtheme");

        /** @var ThemePackage $package */
        $package = ThemePackage::query()->create([
            'source' => 'custom_upload',
            'trust_level' => 'custom',
            'status' => 'quarantined',
            'original_filename' => $file->getClientOriginalName(),
            'quarantine_disk' => 'local',
            'quarantine_path' => $path,
            'sha256' => $hash,
            'size_bytes' => $file->getSize(),
        ]);

        $inspection = $this->inspectPackage($package);
        $package->forceFill([
            'status' => $inspection['status'] === 'passed' ? 'validated' : 'failed',
            'manifest' => $inspection['manifest'],
            'file_index' => $inspection['file_index'],
            'security_report' => $inspection,
            'validated_at' => now(),
        ])->save();

        ThemeCompatibilityReport::query()->create([
            'theme_package_id' => $package->id,
            'status' => $inspection['status'],
            'stages' => $inspection['stages'],
            'errors' => $inspection['errors'],
            'warnings' => $inspection['warnings'],
            'summary' => $inspection['summary'],
        ]);

        return $package->refresh()->load('report');
    }

    public function install(ThemePackage $package): StoreTheme
    {
        $package = ThemePackage::query()->with('report')->whereKey($package->id)->firstOrFail();
        if ($package->status !== 'validated' || ($package->report?->status !== 'passed')) {
            throw ValidationException::withMessages(['theme' => 'Tema paketi doğrulamadan geçmeden kurulamaz.']);
        }

        return DB::transaction(function () use ($package): StoreTheme {
            $manifest = $package->manifest ?? [];
            $publisherNamespace = $this->publisherNamespace($manifest);

            if ($publisherNamespace === 'rivaify' && ($package->trust_level !== 'official')) {
                throw ValidationException::withMessages(['theme' => 'Resmi Rivaify namespace imzasız paketler tarafından kullanılamaz.']);
            }

            $publisher = ThemePublisher::query()->firstOrCreate(
                ['namespace' => $publisherNamespace],
                ['name' => $manifest['author']['name'] ?? Str::headline($publisherNamespace), 'trust_level' => 'custom'],
            );

            /** @var Theme $theme */
            $theme = Theme::query()->firstOrCreate(
                ['key' => $this->themeKey($manifest)],
                [
                    'name' => $manifest['name'],
                    'version' => $manifest['version'],
                    'category' => $manifest['categories'][0] ?? 'custom',
                    'defaults' => ['tokens' => $manifest['tokens'] ?? StorefrontBuilderService::defaultTokens()],
                ],
            );

            $artifactPayload = $this->compilePackage($package);
            /** @var ThemeCompiledArtifact $artifact */
            $artifact = ThemeCompiledArtifact::query()->create([
                'theme_id' => $theme->id,
                'theme_package_id' => $package->id,
                'engine_version' => self::ENGINE_VERSION,
                'artifact_version' => '1',
                'checksum' => hash('sha256', json_encode($artifactPayload, JSON_THROW_ON_ERROR)),
                'artifact' => $artifactPayload,
            ]);

            /** @var ThemeRelease $release */
            $release = ThemeRelease::query()->create([
                'theme_id' => $theme->id,
                'publisher_id' => $publisher->id,
                'version' => $manifest['version'],
                'engine_constraint' => $manifest['engine'],
                'api_version' => $manifest['apiVersion'] ?? '2026-08',
                'riva_lang_version' => $manifest['rivaLang'] ?? '1.0',
                'manifest' => $manifest,
                'compiled_artifact_id' => $artifact->id,
                'status' => 'installed',
            ]);

            $artifact->forceFill(['theme_release_id' => $release->id])->save();

            /** @var StoreTheme $storeTheme */
            $storeTheme = StoreTheme::query()->create([
                'theme_id' => $theme->id,
                'theme_release_id' => $release->id,
                'name' => "{$manifest['name']} v{$manifest['version']}",
                'status' => 'draft',
                'source' => 'custom_upload',
                'trust_level' => 'custom',
                'tokens' => $manifest['tokens'] ?? StorefrontBuilderService::defaultTokens(),
            ]);

            BuilderDocument::query()->create([
                'store_theme_id' => $storeTheme->id,
                'resource_type' => 'home',
                'resource_key' => 'home',
                'schema_version' => 1,
                'document' => $this->defaultDocumentFromManifest($manifest),
                'revision' => 1,
            ]);

            $package->forceFill([
                'theme_id' => $theme->id,
                'theme_release_id' => $release->id,
                'status' => 'installed',
                'installed_at' => now(),
            ])->save();

            return $storeTheme->load(['baseTheme', 'release', 'documents']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function presentPackage(ThemePackage $package): array
    {
        $report = $package->report;

        return [
            'id' => $package->ulid,
            'status' => $package->status,
            'filename' => $package->original_filename,
            'size_bytes' => $package->size_bytes,
            'sha256' => $package->sha256,
            'manifest' => $package->manifest,
            'report' => $report ? [
                'status' => $report->status,
                'stages' => $report->stages,
                'errors' => $report->errors ?? [],
                'warnings' => $report->warnings ?? [],
                'summary' => $report->summary ?? [],
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inspectPackage(ThemePackage $package): array
    {
        $errors = [];
        $warnings = [];
        $stages = $this->stages();
        $manifest = null;
        $fileIndex = [];
        $compiledTemplates = [];
        $totalUncompressed = 0;
        $zip = new ZipArchive();
        $path = Storage::disk($package->quarantine_disk)->path($package->quarantine_path);

        if ($zip->open($path) !== true) {
            $errors[] = $this->error('Package', 'Paket açılamadı. .rivtheme geçerli bir RivaTheme konteyneri olmalıdır.');
            return $this->inspectionResult($stages, $errors, $warnings, null, [], []);
        }

        if ($zip->numFiles > self::MAX_FILES) {
            $errors[] = $this->error('Package', 'Paket çok fazla dosya içeriyor.');
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            if (! $stat) {
                continue;
            }

            $name = str_replace('\\', '/', $stat['name']);
            $fileIndex[] = ['path' => $name, 'size' => $stat['size'] ?? 0, 'checksum' => $stat['crc'] ?? null];

            if (! $this->safePath($name)) {
                $errors[] = $this->error('Security', "Güvensiz dosya yolu: {$name}");
            }

            if (! str_ends_with($name, '/')) {
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (in_array($extension, self::FORBIDDEN_EXTENSIONS, true) || ! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                    $errors[] = $this->error('FileValidation', "Desteklenmeyen veya yasak dosya türü: {$name}");
                }
            }

            $totalUncompressed += (int) ($stat['size'] ?? 0);
            if (($stat['size'] ?? 0) > self::MAX_FILE_BYTES) {
                $warnings[] = $this->warning('Performance', "Büyük tema dosyası: {$name}");
            }
        }

        if ($totalUncompressed > self::MAX_UNCOMPRESSED_BYTES) {
            $errors[] = $this->error('Package', 'Paket açılmış boyut sınırını aşıyor.');
        }

        $manifestSource = $zip->getFromName('riva.theme.json');
        if (! is_string($manifestSource)) {
            $errors[] = $this->error('Manifest', 'riva.theme.json bulunamadı.');
        } else {
            $manifest = json_decode($manifestSource, true);
            if (! is_array($manifest)) {
                $errors[] = $this->error('Manifest', 'riva.theme.json geçerli JSON değil.');
            } else {
                $errors = [...$errors, ...$this->validateManifest($manifest)];
            }
        }

        if (is_array($manifest)) {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = $stat ? str_replace('\\', '/', $stat['name']) : '';
                if (! str_ends_with($name, '.riva')) {
                    continue;
                }

                $source = $zip->getFromIndex($index);
                if (! is_string($source)) {
                    $errors[] = $this->error('RivaLang', "{$name} okunamadı.");
                    continue;
                }

                $compiled = $this->compiler->compile($name, $source);
                $compiledTemplates[$name] = $compiled['ir'];
                foreach ($compiled['diagnostics'] as $diagnostic) {
                    $target = ($diagnostic['level'] ?? 'error') === 'warning' ? 'warnings' : 'errors';
                    ${$target}[] = [
                        'stage' => 'RivaLang',
                        'file' => $diagnostic['file'],
                        'line' => $diagnostic['line'],
                        'column' => $diagnostic['column'],
                        'message' => $diagnostic['message'],
                        'suggestion' => $diagnostic['suggestion'],
                    ];
                }
            }
        }

        $zip->close();

        return $this->inspectionResult($stages, $errors, $warnings, $manifest, $fileIndex, $compiledTemplates);
    }

    /**
     * @return array<string, mixed>
     */
    private function compilePackage(ThemePackage $package): array
    {
        $inspection = $package->security_report ?? [];

        return [
            'engine' => self::ENGINE_VERSION,
            'manifest' => $package->manifest,
            'templates' => $inspection['compiled_templates'] ?? [],
            'sections' => $package->manifest['sections'] ?? [],
            'tokens' => $package->manifest['tokens'] ?? StorefrontBuilderService::defaultTokens(),
            'locales' => $this->localeSummary($package->file_index ?? []),
            'asset_map' => $this->assetMap($package->file_index ?? []),
            'limits' => [
                'max_loop_iterations' => 50,
                'max_nested_loops' => 4,
                'max_generated_nodes' => 2000,
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function validateManifest(array $manifest): array
    {
        $errors = [];
        foreach (['schemaVersion', 'id', 'name', 'version', 'engine', 'templates', 'permissions'] as $key) {
            if (! array_key_exists($key, $manifest)) {
                $errors[] = $this->error('Manifest', "Manifest alanı eksik: {$key}");
            }
        }

        if (($manifest['schemaVersion'] ?? null) !== 1) {
            $errors[] = $this->error('Manifest', 'schemaVersion 1 olmalıdır.');
        }

        if (isset($manifest['id']) && ! preg_match('/^[a-z0-9][a-z0-9-]{2,80}$/', (string) $manifest['id'])) {
            $errors[] = $this->error('Manifest', 'Theme id sadece küçük harf, rakam ve tire içermelidir.');
        }

        if (isset($manifest['version']) && ! preg_match('/^\\d+\\.\\d+\\.\\d+$/', (string) $manifest['version'])) {
            $errors[] = $this->error('Manifest', 'Theme version semver formatında olmalıdır: MAJOR.MINOR.PATCH.');
        }

        if (isset($manifest['engine']) && ! $this->engineCompatible((string) $manifest['engine'])) {
            $errors[] = $this->error('Engine', 'Theme requires an unsupported RivaTheme Engine version. Current platform supports 2.x.');
        }

        $permissions = $manifest['permissions'] ?? [];
        foreach (['network', 'arbitraryJavaScript', 'serverExecution'] as $permission) {
            if (($permissions[$permission] ?? false) !== false) {
                $errors[] = $this->error('Security', "Tema izni yasak: permissions.{$permission}");
            }
        }

        return $errors;
    }

    private function engineCompatible(string $constraint): bool
    {
        $constraint = trim($constraint);

        return $constraint === '2'
            || $constraint === '2.0'
            || $constraint === '2.x'
            || str_starts_with($constraint, '^2.')
            || str_starts_with($constraint, '>=2');
    }

    private function safePath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\0")) {
            return false;
        }

        foreach (explode('/', $path) as $part) {
            if ($part === '..' || $part === '') {
                return false;
            }
        }

        return ! str_contains($path, '../');
    }

    /**
     * @return array<string, mixed>
     */
    private function inspectionResult(array $stages, array $errors, array $warnings, ?array $manifest, array $fileIndex, array $compiledTemplates): array
    {
        $errorStages = collect($errors)->pluck('stage')->unique()->all();
        $warningStages = collect($warnings)->pluck('stage')->unique()->all();
        $stages = array_map(function (array $stage) use ($errorStages, $warningStages): array {
            if (in_array($stage['key'], $errorStages, true)) {
                return [...$stage, 'status' => 'failed'];
            }
            if (in_array($stage['key'], $warningStages, true)) {
                return [...$stage, 'status' => 'warning'];
            }

            return [...$stage, 'status' => 'passed'];
        }, $stages);

        return [
            'status' => $errors === [] ? 'passed' : 'failed',
            'stages' => $stages,
            'errors' => $errors,
            'warnings' => $warnings,
            'manifest' => $manifest,
            'file_index' => $fileIndex,
            'compiled_templates' => $compiledTemplates,
            'summary' => [
                'templates' => collect($fileIndex)->filter(fn ($file) => str_starts_with($file['path'], 'templates/') && str_ends_with($file['path'], '.riva'))->count(),
                'sections' => is_array($manifest['sections'] ?? null) ? count($manifest['sections']) : 0,
                'languages' => collect($fileIndex)->filter(fn ($file) => str_starts_with($file['path'], 'locales/') && str_ends_with($file['path'], '.json'))->count(),
                'assets' => collect($fileIndex)->filter(fn ($file) => str_starts_with($file['path'], 'assets/'))->count(),
            ],
        ];
    }

    private function error(string $stage, string $message): array
    {
        return ['stage' => $stage, 'message' => $message];
    }

    private function warning(string $stage, string $message): array
    {
        return ['stage' => $stage, 'message' => $message];
    }

    private function stages(): array
    {
        return collect(['Package', 'Manifest', 'Engine', 'FileValidation', 'RivaLang', 'Schema', 'Assets', 'Security', 'Performance', 'Compile'])
            ->map(fn (string $stage): array => ['key' => $stage, 'label' => $stage, 'status' => 'pending'])
            ->all();
    }

    private function themeKey(array $manifest): string
    {
        return $this->publisherNamespace($manifest).'/'.$manifest['id'];
    }

    private function publisherNamespace(array $manifest): string
    {
        $author = $manifest['author']['namespace'] ?? null;
        if (is_string($author) && preg_match('/^[a-z0-9][a-z0-9-]{1,60}$/', $author)) {
            return $author;
        }

        return 'local-store';
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultDocumentFromManifest(array $manifest): array
    {
        $sections = collect($manifest['sections'] ?? [])
            ->filter(fn ($section): bool => is_array($section) && is_string($section['type'] ?? null))
            ->take(12)
            ->map(fn (array $section, int $index): array => [
                'id' => 'sec_'.Str::slug($section['type']).'_'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'type' => $section['type'],
                'enabled' => true,
                'settings' => collect($section['settings'] ?? [])
                    ->filter(fn ($setting): bool => is_array($setting) && is_string($setting['id'] ?? null))
                    ->mapWithKeys(fn (array $setting): array => [$setting['id'] => $setting['default'] ?? null])
                    ->all(),
                'blocks' => [],
            ])
            ->values()
            ->all();

        if ($sections === []) {
            $sections = StorefrontBuilderService::defaultHomeDocument($this->currentStore->store())['sections'];
        }

        return ['version' => 1, 'template' => 'home', 'sections' => $sections];
    }

    private function localeSummary(array $files): array
    {
        return collect($files)
            ->pluck('path')
            ->filter(fn ($path): bool => str_starts_with($path, 'locales/') && str_ends_with($path, '.json'))
            ->map(fn ($path): string => basename($path, '.json'))
            ->values()
            ->all();
    }

    private function assetMap(array $files): array
    {
        return collect($files)
            ->pluck('path')
            ->filter(fn ($path): bool => str_starts_with($path, 'assets/'))
            ->values()
            ->all();
    }
}
