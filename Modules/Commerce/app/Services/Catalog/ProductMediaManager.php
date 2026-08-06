<?php

namespace Modules\Commerce\Services\Catalog;

use App\Core\Tenancy\CurrentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Commerce\Exceptions\Catalog\CrossStoreProductMediaException;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Catalog\ProductMedia;

class ProductMediaManager
{
    private const DISK = 'r2';

    public function __construct(private readonly CurrentStore $currentStore) {}

    public function upload(Product $product, UploadedFile $file, ?string $altText = null): ProductMedia
    {
        $this->assertProductBelongsToCurrentStore($product);
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $path = Storage::disk(self::DISK)->putFileAs(
            "stores/{$this->currentStore->store()->ulid}/products/{$product->ulid}",
            $file,
            Str::ulid().'.'.$extension,
        );
        if ($path === false) {
            throw new \RuntimeException('Product media could not be stored.');
        }

        try {
            return DB::transaction(function () use ($product, $file, $path, $altText) {
                $product = Product::query()->lockForUpdate()->findOrFail($product->id);
                $dimensions = @getimagesize($file->getRealPath()) ?: null;
                $position = (int) ($product->media()->max('position') ?? -1) + 1;

                return $product->media()->create([
                    'storage_disk' => self::DISK,
                    'storage_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                    'size_bytes' => $file->getSize() ?? 0,
                    'width' => $dimensions[0] ?? null,
                    'height' => $dimensions[1] ?? null,
                    'alt_text' => $this->nullableText($altText),
                    'position' => $position,
                    'is_featured' => ! $product->media()->where('is_featured', true)->exists(),
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk(self::DISK)->delete($path);

            throw $exception;
        }
    }

    public function updateMetadata(Product $product, ProductMedia $media, ?string $altText, bool $isFeatured): ProductMedia
    {
        $this->assertMediaBelongsToProduct($product, $media);

        return DB::transaction(function () use ($product, $media, $altText, $isFeatured) {
            $media = $product->media()->lockForUpdate()->findOrFail($media->id);
            if ($isFeatured) {
                $product->media()->where('is_featured', true)->update(['is_featured' => false]);
            }
            $media->update([
                'alt_text' => $this->nullableText($altText),
                'is_featured' => $isFeatured,
            ]);

            return $media->refresh();
        });
    }

    /**
     * @param  int[]  $mediaIds
     */
    public function reorder(Product $product, array $mediaIds): void
    {
        $this->assertProductBelongsToCurrentStore($product);

        DB::transaction(function () use ($product, $mediaIds) {
            $media = $product->media()->lockForUpdate()->get()->keyBy('id');
            $requested = array_values(array_unique($mediaIds));
            if (count($requested) !== $media->count() || array_diff($media->keys()->all(), $requested) !== []) {
                throw new CrossStoreProductMediaException('Media reorder payload does not match this product.');
            }

            foreach ($requested as $position => $mediaId) {
                $media->get($mediaId)->update(['position' => $position]);
            }
        });
    }

    public function delete(Product $product, ProductMedia $media): void
    {
        $this->assertMediaBelongsToProduct($product, $media);

        DB::transaction(function () use ($product, $media) {
            $media = $product->media()->lockForUpdate()->findOrFail($media->id);
            $wasFeatured = $media->is_featured;
            Storage::disk($media->storage_disk)->delete($media->storage_path);
            $media->delete();

            if ($wasFeatured) {
                $product->media()->orderBy('position')->first()?->update(['is_featured' => true]);
            }
        });
    }

    private function assertProductBelongsToCurrentStore(Product $product): void
    {
        if ($product->store_id !== $this->currentStore->id()) {
            throw new CrossStoreProductMediaException('Product does not belong to the current store.');
        }
    }

    private function assertMediaBelongsToProduct(Product $product, ProductMedia $media): void
    {
        $this->assertProductBelongsToCurrentStore($product);
        if ($media->store_id !== $product->store_id || $media->product_id !== $product->id) {
            throw new CrossStoreProductMediaException('Media does not belong to this product.');
        }
    }

    private function nullableText(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}