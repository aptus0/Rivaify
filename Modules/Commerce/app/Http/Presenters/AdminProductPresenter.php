<?php

namespace Modules\Commerce\Http\Presenters;

use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Catalog\ProductMedia;
use Modules\Commerce\Models\Catalog\ProductVariant;

class AdminProductPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(Product $product): array
    {
        $product->loadMissing(['category', 'brand', 'media', 'variants.inventoryItem.levels']);

        return [
            'id' => $product->ulid,
            'title' => $product->title,
            'slug' => $product->slug,
            'status' => $product->status->value,
            'product_type' => $product->product_type->value,
            'variant_count' => $product->variants->count(),
            'featured_media' => self::media($product->media->firstWhere('is_featured') ?? $product->media->first(), $product),
            'inventory' => self::inventory($product),
            'category' => self::organization($product->category),
            'brand' => self::organization($product->brand),
            'sales_channels' => [self::storefrontChannel($product)],
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Product $product): array
    {
        $product->loadMissing([
            'category', 'brand', 'tags', 'media', 'options.values',
            'variants.inventoryItem.levels.location',
        ]);

        return [
            ...self::summary($product),
            'description' => $product->description,
            'vendor' => $product->vendor,
            'is_taxable' => $product->is_taxable,
            'requires_shipping' => $product->requires_shipping,
            'package' => [
                'width' => $product->package_width,
                'height' => $product->package_height,
                'length' => $product->package_length,
                'dimension_unit' => $product->package_dimension_unit,
            ],
            'seo' => [
                'meta_title' => $product->meta_title,
                'meta_description' => $product->meta_description,
                'slug' => $product->slug,
            ],
            'tags' => $product->tags->pluck('name')->values()->all(),
            'media' => $product->media->map(fn (ProductMedia $media): array => self::media($media, $product))->values()->all(),
            'options' => $product->options->map(fn ($option): array => [
                'id' => $option->ulid,
                'name' => $option->name,
                'values' => $option->values->map(fn ($value): array => [
                    'id' => $value->ulid,
                    'value' => $value->value,
                    'position' => $value->position,
                ])->values()->all(),
            ])->values()->all(),
            'variants' => $product->variants->map(fn (ProductVariant $variant): array => self::variant($variant))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function variant(ProductVariant $variant): array
    {
        $levels = $variant->inventoryItem?->levels ?? collect();
        $priceMinor = self::minorAmount($variant->price);
        $costMinor = $variant->cost_price === null ? null : self::minorAmount($variant->cost_price);
        $profitMinor = $costMinor === null ? null : $priceMinor - $costMinor;
        $marginBasisPoints = $profitMinor === null || $priceMinor === 0 ? null : intdiv($profitMinor * 10_000, $priceMinor);

        return [
            'id' => $variant->ulid,
            'title' => $variant->title,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'price' => $variant->price,
            'compare_at_price' => $variant->compare_at_price,
            'cost_price' => $variant->cost_price,
            'profit' => $profitMinor === null ? null : self::decimalAmount($profitMinor),
            'margin_percent' => $marginBasisPoints === null ? null : self::decimalAmount($marginBasisPoints, 2),
            'weight' => $variant->weight,
            'weight_unit' => $variant->weight_unit,
            'requires_shipping' => $variant->requires_shipping,
            'is_taxable' => $variant->is_taxable,
            'status' => $variant->status->value,
            'inventory' => [
                'is_tracked' => $variant->inventoryItem?->is_tracked ?? false,
                'allow_oversell' => $variant->inventoryItem?->allow_oversell ?? false,
                'available' => $levels->sum('available_quantity'),
                'reserved' => $levels->sum('reserved_quantity'),
                'incoming' => $levels->sum('incoming_quantity'),
                'sellable' => $levels->sum(fn ($level): int => $level->sellableQuantity()),
                'levels' => $levels->map(fn ($level): array => [
                    'location_id' => $level->location->ulid,
                    'location_name' => $level->location->name,
                    'available' => $level->available_quantity,
                    'reserved' => $level->reserved_quantity,
                    'incoming' => $level->incoming_quantity,
                    'sellable' => $level->sellableQuantity(),
                ])->values()->all(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function inventory(Product $product): array
    {
        $trackedVariants = $product->variants->filter(fn (ProductVariant $variant): bool => $variant->inventoryItem?->is_tracked ?? false);
        $sellable = $trackedVariants->sum(fn (ProductVariant $variant): int => $variant->inventoryItem->levels->sum(
            fn ($level): int => $level->sellableQuantity(),
        ));

        return [
            'is_tracked' => $trackedVariants->isNotEmpty(),
            'sellable' => $sellable,
            'status' => $trackedVariants->isEmpty() ? 'not_tracked' : ($sellable === 0 ? 'out_of_stock' : ($sellable <= 5 ? 'low_stock' : 'in_stock')),
        ];
    }

    /**
     * @return array{key: string, label: string, enabled: bool, status: string, detail: string}
     */
    private static function storefrontChannel(Product $product): array
    {
        $activeVariants = $product->variants->filter(fn (ProductVariant $variant): bool => $variant->status === ProductStatus::Active)->count();
        $published = $product->published_at === null || $product->published_at->isPast();
        $enabled = $product->status === ProductStatus::Active && $published && $activeVariants > 0;
        $detail = match (true) {
            $product->status !== ProductStatus::Active => 'Ürün aktif değil',
            ! $published => 'Yayın zamanı bekleniyor',
            $activeVariants === 0 => 'Aktif varyant yok',
            default => 'Yayında',
        };

        return [
            'key' => 'online_store',
            'label' => 'Online Mağaza',
            'enabled' => $enabled,
            'status' => $enabled ? 'published' : 'not_ready',
            'detail' => $detail,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function media(?ProductMedia $media, Product $product): ?array
    {
        if ($media === null) {
            return null;
        }

        return [
            'id' => $media->ulid,
            'media_type' => $media->media_type,
            'url' => "/api/v1/products/{$product->ulid}/media/{$media->ulid}/file",
            'original_filename' => $media->original_filename,
            'mime_type' => $media->mime_type,
            'size_bytes' => $media->size_bytes,
            'width' => $media->width,
            'height' => $media->height,
            'alt_text' => $media->alt_text,
            'position' => $media->position,
            'is_featured' => $media->is_featured,
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private static function organization($model): ?array
    {
        return $model === null ? null : ['id' => $model->ulid, 'name' => $model->name];
    }

    private static function minorAmount(string $amount): int
    {
        $negative = str_starts_with($amount, '-');
        $amount = ltrim($amount, '-');
        [$major, $minor] = array_pad(explode('.', $amount, 2), 2, '0');
        $value = ((int) $major * 100) + (int) str_pad($minor, 2, '0');

        return $negative ? -$value : $value;
    }

    private static function decimalAmount(int $minorAmount, int $scale = 2): string
    {
        $negative = $minorAmount < 0;
        $minorAmount = abs($minorAmount);
        $factor = 10 ** $scale;

        return ($negative ? '-' : '').intdiv($minorAmount, $factor).'.'.str_pad((string) ($minorAmount % $factor), $scale, '0', STR_PAD_LEFT);
    }
}
