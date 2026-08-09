<?php

namespace Modules\Commerce\Services\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\DTOs\Catalog\ProductEditorData;
use Modules\Commerce\DTOs\Catalog\ProductOptionInputData;
use Modules\Commerce\DTOs\Catalog\ProductVariantEditorData;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Enums\Catalog\ProductType;
use Modules\Commerce\Exceptions\Catalog\CrossStoreAssignmentException;
use Modules\Commerce\Exceptions\Catalog\InvalidProductOptionsException;
use Modules\Commerce\Exceptions\Catalog\InvalidProductVariantDataException;
use Modules\Commerce\Models\Catalog\Brand;
use Modules\Commerce\Models\Catalog\Category;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Catalog\ProductVariant;
use Modules\Commerce\Models\Inventory\InventoryLocation;
use Throwable;

class ProductCsvManager
{
    public const MAX_ROWS = 1000;

    public const HEADERS = [
        'product_id', 'handle', 'title', 'description', 'status', 'product_type', 'vendor',
        'category', 'brand', 'tags', 'is_taxable', 'requires_shipping', 'meta_title',
        'meta_description', 'package_width', 'package_height', 'package_length',
        'package_dimension_unit', 'option1_name', 'option1_value', 'option2_name',
        'option2_value', 'option3_name', 'option3_value', 'variant_title', 'variant_sku',
        'variant_barcode', 'variant_price', 'variant_compare_at_price', 'variant_cost_price',
        'variant_weight', 'variant_weight_unit', 'variant_status', 'variant_is_taxable',
        'variant_requires_shipping', 'track_inventory', 'allow_oversell', 'inventory_location',
        'inventory_quantity',
    ];

    private const MAX_RETURNED_ERRORS = 500;

    public function __construct(
        private readonly ProductEditor $editor,
        private readonly ProductDescriptionSanitizer $descriptions,
    ) {}

    public function writeExport(Builder $query): void
    {
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new \RuntimeException('CSV output stream could not be opened.');
        }

        // UTF-8 BOM keeps Turkish characters intact in Excel without changing
        // the actual CSV encoding or delimiter.
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, self::HEADERS, ',', '"', '', "\r\n");

        $query->lazyById(100, 'products.id', 'id')->each(function (Product $product) use ($output): void {
            $variants = $product->variants;
            if ($variants->isEmpty()) {
                fputcsv($output, $this->exportRow($product, null, null), ',', '"', '', "\r\n");

                return;
            }

            foreach ($variants as $variant) {
                $levels = $variant->inventoryItem?->is_tracked ? $variant->inventoryItem->levels : null;
                if ($levels === null || $levels->isEmpty()) {
                    fputcsv($output, $this->exportRow($product, $variant, null), ',', '"', '', "\r\n");

                    continue;
                }
                foreach ($levels as $level) {
                    fputcsv($output, $this->exportRow($product, $variant, $level), ',', '"', '', "\r\n");
                }
            }
        });

        fclose($output);
    }

    /**
     * @return array<string, mixed>
     */
    public function process(UploadedFile $file, bool $commit): array
    {
        $parsed = $this->parse($file);
        $errors = $parsed['errors'];
        $groups = $this->buildGroups($parsed['rows'], $errors);

        $willCreate = count(array_filter($groups, fn (array $group): bool => $group['product'] === null));
        $willUpdate = count($groups) - $willCreate;
        $created = 0;
        $updated = 0;
        $failed = 0;

        // Two phase strategy: no database writes start while any deterministic
        // file/row validation error exists. During commit every product group
        // gets its own transaction, so one unexpected service error cannot
        // leave a half-created product or half-adjusted inventory ledger.
        if ($commit && $errors === []) {
            foreach ($groups as $group) {
                try {
                    DB::transaction(function () use ($group): void {
                        $data = $this->editorData($group);
                        if ($group['product'] instanceof Product) {
                            $this->editor->update($group['product'], $data);
                        } else {
                            $this->editor->create($data);
                        }
                    });
                    $group['product'] instanceof Product ? $updated++ : $created++;
                } catch (CrossStoreAssignmentException|InvalidProductOptionsException|InvalidProductVariantDataException|\InvalidArgumentException $exception) {
                    $failed++;
                    $errors[] = $this->error($group['first_row'], 'product', $exception->getMessage(), $group['handle']);
                } catch (Throwable $exception) {
                    report($exception);
                    $failed++;
                    $errors[] = $this->error($group['first_row'], 'product', 'Ürün beklenmeyen bir servis hatası nedeniyle aktarılamadı.', $group['handle']);
                }
            }
        }

        $errorCount = count($errors);

        return [
            'mode' => $commit ? 'commit' : 'preview',
            'file_name' => $file->getClientOriginalName(),
            'row_count' => $parsed['row_count'],
            'product_count' => count($groups),
            'will_create' => $willCreate,
            'will_update' => $willUpdate,
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
            'can_import' => $errorCount === 0,
            'error_count' => $errorCount,
            'errors_truncated' => $errorCount > self::MAX_RETURNED_ERRORS,
            'errors' => array_slice($errors, 0, self::MAX_RETURNED_ERRORS),
        ];
    }

    /**
     * @return array{rows: array<int, array{row: int, data: array<string, string>}>, row_count: int, errors: array<int, array<string, mixed>>}
     */
    private function parse(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            throw ValidationException::withMessages(['file' => ['CSV dosyası okunamadı.']]);
        }
        $contents = file_get_contents($path);
        if ($contents === false || $contents === '') {
            throw ValidationException::withMessages(['file' => ['CSV dosyası boş olamaz.']]);
        }
        if (! mb_check_encoding($contents, 'UTF-8')) {
            throw ValidationException::withMessages(['file' => ['CSV UTF-8 kodlamasında olmalıdır.']]);
        }
        if (str_contains($contents, "\0")) {
            throw ValidationException::withMessages(['file' => ['CSV dosyası geçersiz null byte içeriyor.']]);
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['file' => ['CSV dosyası açılamadı.']]);
        }
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => ['CSV başlık satırı bulunamadı.']]);
        }
        $delimiter = $this->detectDelimiter($firstLine);
        rewind($handle);
        $headers = fgetcsv($handle, 0, $delimiter, '"', '');
        if (! is_array($headers)) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => ['CSV başlık satırı okunamadı.']]);
        }
        $headers = array_map(fn (mixed $header): string => mb_strtolower(trim($this->stripBom((string) $header))), $headers);
        $this->validateHeaders($headers);

        $rows = [];
        $errors = [];
        $record = 1;
        while (($values = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $record++;
            if ($this->isBlankRow($values)) {
                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                fclose($handle);
                throw ValidationException::withMessages(['file' => ['CSV en fazla '.self::MAX_ROWS.' veri satırı içerebilir.']]);
            }
            if (count($values) !== count($headers)) {
                $errors[] = $this->error($record, 'columns', 'Kolon sayısı başlık satırıyla eşleşmiyor.');

                continue;
            }

            /** @var array<string, string> $data */
            $data = array_combine($headers, array_map(fn (mixed $value): string => trim((string) $value), $values));
            $rowErrors = $this->validateRow($record, $data);
            array_push($errors, ...$rowErrors);
            if ($rowErrors === []) {
                $rows[] = ['row' => $record, 'data' => $data];
            }
        }
        fclose($handle);

        if ($rows === [] && $errors === []) {
            throw ValidationException::withMessages(['file' => ['CSV en az bir veri satırı içermelidir.']]);
        }

        return ['rows' => $rows, 'row_count' => count($rows) + count(array_unique(array_column($errors, 'row'))), 'errors' => $errors];
    }

    /**
     * @param  string[]  $headers
     */
    private function validateHeaders(array $headers): void
    {
        if (count($headers) !== count(array_unique($headers))) {
            throw ValidationException::withMessages(['file' => ['CSV başlıkları tekrar edemez.']]);
        }
        $missing = array_values(array_diff(self::HEADERS, $headers));
        $unknown = array_values(array_diff($headers, self::HEADERS));
        $messages = [];
        if ($missing !== []) {
            $messages[] = 'Eksik başlıklar: '.implode(', ', $missing).'.';
        }
        if ($unknown !== []) {
            $messages[] = 'Desteklenmeyen başlıklar: '.implode(', ', $unknown).'.';
        }
        if ($messages !== []) {
            throw ValidationException::withMessages(['file' => $messages]);
        }
    }

    /**
     * @param  array<string, string>  $data
     * @return array<int, array<string, mixed>>
     */
    private function validateRow(int $row, array $data): array
    {
        $statuses = implode(',', array_map(fn (ProductStatus $status) => $status->value, ProductStatus::cases()));
        $types = implode(',', array_map(fn (ProductType $type) => $type->value, ProductType::cases()));
        $validator = Validator::make($data, [
            'product_id' => ['nullable', 'string', 'size:26'],
            'handle' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:100000'],
            'status' => ['required', 'in:'.$statuses],
            'product_type' => ['required', 'in:'.$types],
            'vendor' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:5000'],
            'is_taxable' => ['required', 'in:true,false,1,0'],
            'requires_shipping' => ['required', 'in:true,false,1,0'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'package_width' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'package_height' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'package_length' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'package_dimension_unit' => ['required', 'in:cm,in'],
            'option1_name' => ['nullable', 'string', 'max:100'],
            'option1_value' => ['nullable', 'string', 'max:100'],
            'option2_name' => ['nullable', 'string', 'max:100'],
            'option2_value' => ['nullable', 'string', 'max:100'],
            'option3_name' => ['nullable', 'string', 'max:100'],
            'option3_value' => ['nullable', 'string', 'max:100'],
            'variant_title' => ['nullable', 'string', 'max:255'],
            'variant_sku' => ['nullable', 'string', 'max:255'],
            'variant_barcode' => ['nullable', 'string', 'max:255'],
            'variant_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'variant_compare_at_price' => ['nullable', 'numeric', 'decimal:0,2', 'gte:variant_price', 'max:9999999999.99'],
            'variant_cost_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'variant_weight' => ['nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:9999999.999'],
            'variant_weight_unit' => ['required', 'in:g,kg'],
            'variant_status' => ['required', 'in:'.$statuses],
            'variant_is_taxable' => ['required', 'in:true,false,1,0'],
            'variant_requires_shipping' => ['required', 'in:true,false,1,0'],
            'track_inventory' => ['required', 'in:true,false,1,0'],
            'allow_oversell' => ['required', 'in:true,false,1,0'],
            'inventory_location' => ['nullable', 'string', 'max:255'],
            'inventory_quantity' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
        ]);

        $errors = [];
        foreach ($validator->errors()->toArray() as $field => $messages) {
            foreach ($messages as $message) {
                $errors[] = $this->error($row, $field, $message, $data['handle'] ?? null);
            }
        }
        if ($errors !== []) {
            return $errors;
        }

        for ($index = 1; $index <= 3; $index++) {
            $name = $data["option{$index}_name"];
            $value = $data["option{$index}_value"];
            if (($name === '') !== ($value === '')) {
                $errors[] = $this->error($row, "option{$index}", 'Seçenek adı ve değeri birlikte doldurulmalıdır.', $data['handle']);
            }
            if ($index > 1 && $name !== '' && $data['option'.($index - 1).'_name'] === '') {
                $errors[] = $this->error($row, "option{$index}", 'Seçenek kolonları boşluk bırakmadan sıralanmalıdır.', $data['handle']);
            }
        }
        $tags = array_values(array_filter(array_map('trim', explode('|', $data['tags'])), fn (string $tag): bool => $tag !== ''));
        if (count($tags) > 50) {
            $errors[] = $this->error($row, 'tags', 'Bir üründe en fazla 50 etiket olabilir.', $data['handle']);
        }
        foreach ($tags as $tag) {
            if (mb_strlen($tag) > 100) {
                $errors[] = $this->error($row, 'tags', 'Her etiket en fazla 100 karakter olabilir.', $data['handle']);
                break;
            }
        }
        if ($data['product_type'] !== ProductType::Physical->value && $this->boolean($data['requires_shipping'])) {
            $errors[] = $this->error($row, 'requires_shipping', 'Dijital ve hizmet ürünleri kargo gerektiremez.', $data['handle']);
        }
        $hasLocation = $data['inventory_location'] !== '';
        $hasQuantity = $data['inventory_quantity'] !== '';
        if ($hasLocation !== $hasQuantity) {
            $errors[] = $this->error($row, 'inventory', 'Stok lokasyonu ve miktarı birlikte doldurulmalıdır.', $data['handle']);
        }
        if (! $this->boolean($data['track_inventory']) && ($hasLocation || $hasQuantity)) {
            $errors[] = $this->error($row, 'inventory', 'Stok takibi kapalı varyanta lokasyon miktarı verilemez.', $data['handle']);
        }

        return $errors;
    }

    /**
     * @param  array<int, array{row: int, data: array<string, string>}>  $rows
     * @param  array<int, array<string, mixed>>  $errors
     * @return array<int, array<string, mixed>>
     */
    private function buildGroups(array $rows, array &$errors): array
    {
        $rawGroups = [];
        foreach ($rows as $row) {
            $data = $row['data'];
            $key = $data['product_id'] !== '' ? 'id:'.$data['product_id'] : 'new:'.$data['handle'];
            $rawGroups[$key][] = $row;
        }

        $groups = [];
        $seenSkus = [];
        $seenBarcodes = [];
        $seenHandles = [];
        foreach ($rawGroups as $groupKey => $groupRows) {
            $handle = $groupRows[0]['data']['handle'];
            if (isset($seenHandles[$handle]) && $seenHandles[$handle] !== $groupKey) {
                $errors[] = $this->error($groupRows[0]['row'], 'handle', 'Aynı handle CSV içinde farklı product_id değerleriyle kullanılamaz.', $handle);
            } else {
                $seenHandles[$handle] = $groupKey;
            }
            $group = $this->buildGroup($groupRows, $errors, $seenSkus, $seenBarcodes);
            if ($group !== null) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /**
     * @param  array<int, array{row: int, data: array<string, string>}>  $rows
     * @param  array<int, array<string, mixed>>  $errors
     * @param  array<string, array{key: string, row: int}>  $seenSkus
     * @param  array<string, array{key: string, row: int}>  $seenBarcodes
     * @return array<string, mixed>|null
     */
    private function buildGroup(array $rows, array &$errors, array &$seenSkus, array &$seenBarcodes): ?array
    {
        $first = $rows[0];
        $base = $first['data'];
        $handle = $base['handle'];
        $errorCountBefore = count($errors);
        $productFields = [
            'product_id', 'handle', 'title', 'description', 'status', 'product_type', 'vendor',
            'category', 'brand', 'tags', 'is_taxable', 'requires_shipping', 'meta_title',
            'meta_description', 'package_width', 'package_height', 'package_length', 'package_dimension_unit',
        ];
        foreach ($rows as $row) {
            foreach ($productFields as $field) {
                if ($row['data'][$field] !== $base[$field]) {
                    $errors[] = $this->error($row['row'], $field, 'Aynı ürüne ait satırlarda bu alan aynı olmalıdır.', $handle);
                }
            }
        }

        $product = null;
        if ($base['product_id'] !== '') {
            $product = Product::query()->where('ulid', $base['product_id'])->first();
            if ($product === null) {
                $errors[] = $this->error($first['row'], 'product_id', 'Güncellenecek ürün bu mağazada bulunamadı.', $handle);
            } elseif (Product::query()->where('slug', $handle)->whereKeyNot($product->id)->exists()) {
                $errors[] = $this->error($first['row'], 'handle', 'Bu handle mağazada başka bir üründe kullanılıyor.', $handle);
            }
        } elseif (Product::query()->where('slug', $handle)->exists()) {
            $errors[] = $this->error($first['row'], 'handle', 'Bu handle mağazada zaten var. Güncellemek için dışa aktarılan product_id kullanılmalıdır.', $handle);
        }

        $categoryId = $this->catalogId(Category::class, $base['category']);
        if ($base['category'] !== '' && $categoryId === null) {
            $errors[] = $this->error($first['row'], 'category', 'Kategori slug veya ULID değeri bu mağazada bulunamadı.', $handle);
        }
        $brandId = $this->catalogId(Brand::class, $base['brand']);
        if ($base['brand'] !== '' && $brandId === null) {
            $errors[] = $this->error($first['row'], 'brand', 'Marka slug veya ULID değeri bu mağazada bulunamadı.', $handle);
        }

        $optionNames = [];
        for ($index = 1; $index <= 3; $index++) {
            if ($base["option{$index}_name"] !== '') {
                $optionNames[] = $base["option{$index}_name"];
            }
        }
        if (count($optionNames) !== count(array_unique(array_map('mb_strtolower', $optionNames)))) {
            $errors[] = $this->error($first['row'], 'options', 'Seçenek adları ürün içinde benzersiz olmalıdır.', $handle);
        }

        $variants = [];
        foreach ($rows as $row) {
            $data = $row['data'];
            $rowOptionNames = [];
            $optionValues = [];
            for ($index = 1; $index <= 3; $index++) {
                if ($data["option{$index}_name"] !== '') {
                    $rowOptionNames[] = $data["option{$index}_name"];
                    $optionValues[] = $data["option{$index}_value"];
                }
            }
            if ($rowOptionNames !== $optionNames) {
                $errors[] = $this->error($row['row'], 'options', 'Aynı üründeki seçenek adları ve sırası eşleşmelidir.', $handle);

                continue;
            }
            $generatedTitle = implode(' / ', $optionValues);
            if ($optionValues !== [] && $data['variant_title'] !== '' && $data['variant_title'] !== $generatedTitle) {
                $errors[] = $this->error($row['row'], 'variant_title', "Varyant başlığı seçenek değerleriyle eşleşmelidir: {$generatedTitle}.", $handle);
            }
            $variantKey = $optionValues === [] ? '__default__' : implode("\x1F", $optionValues);
            $variantFields = [
                'variant_title', 'variant_sku', 'variant_barcode', 'variant_price', 'variant_compare_at_price',
                'variant_cost_price', 'variant_weight', 'variant_weight_unit', 'variant_status',
                'variant_is_taxable', 'variant_requires_shipping', 'track_inventory', 'allow_oversell',
            ];
            if (! isset($variants[$variantKey])) {
                $variants[$variantKey] = [
                    'first_row' => $row['row'],
                    'data' => $data,
                    'option_values' => $optionValues,
                    'inventory' => [],
                ];
            } else {
                foreach ($variantFields as $field) {
                    if ($variants[$variantKey]['data'][$field] !== $data[$field]) {
                        $errors[] = $this->error($row['row'], $field, 'Aynı varyantın stok satırlarında bu alan aynı olmalıdır.', $handle);
                    }
                }
            }

            if ($data['inventory_location'] !== '') {
                $location = $this->location($data['inventory_location']);
                if ($location === null) {
                    $errors[] = $this->error($row['row'], 'inventory_location', 'Stok lokasyonu kodu veya ULID değeri bu mağazada bulunamadı.', $handle);
                } elseif (isset($variants[$variantKey]['inventory'][$location->id])) {
                    $errors[] = $this->error($row['row'], 'inventory_location', 'Aynı varyant ve lokasyon CSV içinde tekrar edemez.', $handle);
                } else {
                    $variants[$variantKey]['inventory'][$location->id] = (int) $data['inventory_quantity'];
                }
            }

            $identity = ($base['product_id'] ?: 'new:'.$handle).'|'.$variantKey;
            $this->registerUniqueCode($data['variant_sku'], 'variant_sku', $identity, $row['row'], $handle, $seenSkus, $errors);
            $this->registerUniqueCode($data['variant_barcode'], 'variant_barcode', $identity, $row['row'], $handle, $seenBarcodes, $errors);
        }

        if ($optionNames === [] && count($variants) > 1) {
            $errors[] = $this->error($first['row'], 'options', 'Birden fazla varyant için en az bir seçenek kolonu gereklidir.', $handle);
        }
        if ($optionNames !== []) {
            $optionValueSets = [];
            foreach (array_keys($optionNames) as $position) {
                $optionValueSets[$position] = array_values(array_unique(array_map(
                    fn (array $variant): string => $variant['option_values'][$position],
                    $variants,
                )));
            }
            $expectedVariantCount = array_product(array_map('count', $optionValueSets));
            if ($expectedVariantCount !== count($variants)) {
                $errors[] = $this->error($first['row'], 'options', 'Seçenek değerleri tam bir varyant matrisi oluşturmalıdır.', $handle);
            }
        }

        $currentProductId = $product?->id;
        foreach ($variants as $variant) {
            foreach ([['variant_sku', 'sku'], ['variant_barcode', 'barcode']] as [$field, $column]) {
                $code = $variant['data'][$field];
                if ($code === '') {
                    continue;
                }
                $conflict = ProductVariant::query()
                    ->where($column, $code)
                    ->when($currentProductId !== null, fn (Builder $query) => $query->where('product_id', '!=', $currentProductId))
                    ->exists();
                if ($conflict) {
                    $errors[] = $this->error($variant['first_row'], $field, strtoupper($column).' bu mağazada başka bir üründe kullanılıyor.', $handle);
                }
            }
        }

        if (count($errors) > $errorCountBefore) {
            return null;
        }

        return [
            'first_row' => $first['row'],
            'handle' => $handle,
            'base' => $base,
            'product' => $product,
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'option_names' => $optionNames,
            'variants' => array_values($variants),
        ];
    }

    /**
     * @param  array<string, mixed>  $group
     */
    private function editorData(array $group): ProductEditorData
    {
        $base = $group['base'];
        $options = [];
        foreach ($group['option_names'] as $position => $name) {
            $values = [];
            foreach ($group['variants'] as $variant) {
                $value = $variant['option_values'][$position];
                if (! in_array($value, $values, true)) {
                    $values[] = $value;
                }
            }
            $options[] = new ProductOptionInputData($name, $values);
        }
        $variants = array_map(function (array $variant) use ($base): ProductVariantEditorData {
            $data = $variant['data'];
            $title = $variant['option_values'] === []
                ? ($data['variant_title'] !== '' ? $data['variant_title'] : 'Default')
                : implode(' / ', $variant['option_values']);

            return new ProductVariantEditorData(
                title: $title,
                price: $data['variant_price'],
                compareAtPrice: $this->nullable($data['variant_compare_at_price']),
                costPrice: $this->nullable($data['variant_cost_price']),
                sku: $this->nullable($data['variant_sku']),
                barcode: $this->nullable($data['variant_barcode']),
                weight: $this->nullable($data['variant_weight']),
                weightUnit: $data['variant_weight_unit'],
                requiresShipping: $this->boolean($data['variant_requires_shipping']),
                isTaxable: $this->boolean($data['variant_is_taxable']),
                status: ProductStatus::from($data['variant_status'] ?: $base['status']),
                trackInventory: $this->boolean($data['track_inventory']),
                allowOversell: $this->boolean($data['allow_oversell']),
                inventoryByLocationId: $variant['inventory'],
            );
        }, $group['variants']);

        return new ProductEditorData(
            title: $base['title'],
            description: $this->descriptions->sanitize($this->nullable($base['description'])),
            slug: $base['handle'],
            categoryId: $group['category_id'],
            brandId: $group['brand_id'],
            productType: ProductType::from($base['product_type']),
            status: ProductStatus::from($base['status']),
            vendor: $this->nullable($base['vendor']),
            isTaxable: $this->boolean($base['is_taxable']),
            requiresShipping: $this->boolean($base['requires_shipping']),
            metaTitle: $this->nullable($base['meta_title']),
            metaDescription: $this->nullable($base['meta_description']),
            packageWidth: $this->nullable($base['package_width']),
            packageHeight: $this->nullable($base['package_height']),
            packageLength: $this->nullable($base['package_length']),
            packageDimensionUnit: $base['package_dimension_unit'],
            tags: array_values(array_filter(array_map('trim', explode('|', $base['tags'])))),
            options: $options,
            variants: $variants,
        );
    }

    /**
     * @return array<int, string>
     */
    private function exportRow(Product $product, ?ProductVariant $variant, mixed $level): array
    {
        $pairs = $variant?->variantValues
            ?->sortBy(fn ($pair) => $pair->option?->position ?? 0)
            ->values() ?? collect();
        $row = [
            $product->ulid,
            $this->safeText($product->slug),
            $this->safeText($product->title),
            $this->safeText($product->description),
            $product->status->value,
            $product->product_type->value,
            $this->safeText($product->vendor),
            $product->category?->slug ?? '',
            $product->brand?->slug ?? '',
            $this->safeText($product->tags->pluck('name')->implode('|')),
            $this->csvBoolean($product->is_taxable),
            $this->csvBoolean($product->requires_shipping),
            $this->safeText($product->meta_title),
            $this->safeText($product->meta_description),
            $product->package_width ?? '',
            $product->package_height ?? '',
            $product->package_length ?? '',
            $product->package_dimension_unit ?? 'cm',
        ];
        for ($index = 0; $index < 3; $index++) {
            $row[] = $this->safeText($pairs->get($index)?->option?->name);
            $row[] = $this->safeText($pairs->get($index)?->optionValue?->value);
        }
        array_push($row,
            $this->safeText($variant?->title),
            $this->safeText($variant?->sku),
            $this->safeText($variant?->barcode),
            $variant?->price ?? '0.00',
            $variant?->compare_at_price ?? '',
            $variant?->cost_price ?? '',
            $variant?->weight ?? '',
            $variant?->weight_unit ?? 'kg',
            $variant?->status->value ?? $product->status->value,
            $this->csvBoolean($variant?->is_taxable ?? $product->is_taxable),
            $this->csvBoolean($variant?->requires_shipping ?? $product->requires_shipping),
            $this->csvBoolean($variant?->inventoryItem?->is_tracked ?? false),
            $this->csvBoolean($variant?->inventoryItem?->allow_oversell ?? false),
            $this->safeText($level?->location?->code ?: ($level?->location?->ulid ?? '')),
            $level?->available_quantity !== null ? (string) $level->available_quantity : '',
        );

        return array_map(fn (mixed $value): string => (string) $value, $row);
    }

    /**
     * @param  class-string<Category|Brand>  $model
     */
    private function catalogId(string $model, string $reference): ?int
    {
        if ($reference === '') {
            return null;
        }

        return $model::query()
            ->where(fn (Builder $query) => $query->where('ulid', $reference)->orWhere('slug', $reference))
            ->value('id');
    }

    private function location(string $reference): ?InventoryLocation
    {
        return InventoryLocation::query()
            ->where(fn (Builder $query) => $query
                ->where('ulid', $reference)
                ->orWhereRaw('UPPER(code) = ?', [mb_strtoupper($reference)]))
            ->first();
    }

    /**
     * @param  array<string, array{key: string, row: int}>  $seen
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function registerUniqueCode(
        string $code,
        string $field,
        string $variantKey,
        int $row,
        string $handle,
        array &$seen,
        array &$errors,
    ): void {
        if ($code === '') {
            return;
        }
        if (isset($seen[$code]) && $seen[$code]['key'] !== $variantKey) {
            $errors[] = $this->error($row, $field, 'Bu değer CSV içinde başka bir varyantta tekrar ediyor.', $handle);

            return;
        }
        $seen[$code] = ['key' => $variantKey, 'row' => $row];
    }

    /**
     * @return array{row: int, field: string, message: string, handle: string|null}
     */
    private function error(int $row, string $field, string $message, ?string $handle = null): array
    {
        return ['row' => $row, 'field' => $field, 'message' => $message, 'handle' => $handle ?: null];
    }

    private function boolean(string $value): bool
    {
        return in_array($value, ['true', '1'], true);
    }

    private function csvBoolean(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    private function nullable(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }

    private function safeText(?string $value): string
    {
        $value = $value ?? '';

        return preg_match('/^[=+\-@\t\r]/u', $value) === 1 ? "'{$value}" : $value;
    }

    private function detectDelimiter(string $line): string
    {
        $scores = [];
        foreach ([',', ';', "\t"] as $delimiter) {
            $scores[$delimiter] = count(str_getcsv($line, $delimiter, '"', ''));
        }
        arsort($scores);

        return (string) array_key_first($scores);
    }

    private function stripBom(string $value): string
    {
        return str_starts_with($value, "\xEF\xBB\xBF") ? substr($value, 3) : $value;
    }

    /**
     * @param  array<int, string|null>  $values
     */
    private function isBlankRow(array $values): bool
    {
        return array_filter($values, fn (mixed $value): bool => trim((string) $value) !== '') === [];
    }
}
