<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Commerce\Exceptions\Catalog\CrossStoreProductMediaException;
use Modules\Commerce\Http\Presenters\AdminProductPresenter;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Catalog\ProductMedia;
use Modules\Commerce\Services\Catalog\ProductMediaManager;
use Symfony\Component\HttpFoundation\Response;

class AdminProductMediaController extends Controller
{
    public function store(Request $request, string $productUlid, ProductMediaManager $media): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimetypes:image/jpeg,image/png,image/webp,image/avif'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);
        $product = Product::query()->where('ulid', $productUlid)->firstOrFail();
        $uploaded = $media->upload($product, $validated['file'], $validated['alt_text'] ?? null);

        return response()->json(['data' => $this->mediaPayload($uploaded, $product)], 201);
    }

    public function update(Request $request, string $productUlid, string $mediaUlid, ProductMediaManager $media): JsonResponse
    {
        $validated = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:255'],
            'is_featured' => ['required', 'boolean'],
        ]);
        $product = Product::query()->where('ulid', $productUlid)->firstOrFail();
        $record = $product->media()->where('ulid', $mediaUlid)->firstOrFail();
        $updated = $media->updateMetadata($product, $record, $validated['alt_text'] ?? null, $validated['is_featured']);

        return response()->json(['data' => $this->mediaPayload($updated, $product)]);
    }

    public function reorder(Request $request, string $productUlid, ProductMediaManager $media): JsonResponse
    {
        $validated = $request->validate([
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['string', 'size:26'],
        ]);
        $product = Product::query()->where('ulid', $productUlid)->firstOrFail();
        $records = $product->media()->whereIn('ulid', $validated['media_ids'])->get()->keyBy('ulid');
        if ($records->count() !== count(array_unique($validated['media_ids']))) {
            abort(422, 'product_media_not_found');
        }

        try {
            $media->reorder($product, array_map(
                fn (string $ulid): int => $records->get($ulid)->id,
                $validated['media_ids'],
            ));
        } catch (CrossStoreProductMediaException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => AdminProductPresenter::detail($product->fresh([
            'category', 'brand', 'tags', 'media', 'options.values', 'variants.inventoryItem.levels.location',
        ]))]);
    }

    public function destroy(string $productUlid, string $mediaUlid, ProductMediaManager $media): JsonResponse
    {
        $product = Product::query()->where('ulid', $productUlid)->firstOrFail();
        $record = $product->media()->where('ulid', $mediaUlid)->firstOrFail();
        $media->delete($product, $record);

        return response()->json(status: 204);
    }

    public function file(string $productUlid, string $mediaUlid): Response
    {
        $product = Product::query()->where('ulid', $productUlid)->firstOrFail();
        $media = $product->media()->where('ulid', $mediaUlid)->firstOrFail();
        if (! Storage::disk($media->storage_disk)->exists($media->storage_path)) {
            abort(404);
        }

        return Storage::disk($media->storage_disk)->response($media->storage_path, $media->original_filename, [
            'Content-Type' => $media->mime_type,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaPayload(ProductMedia $media, Product $product): array
    {
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
}