<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Services\Catalog\ProductMediaManager;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Models\Store;
use Tests\TestCase;

class ProductMediaManagerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_product_media_can_be_uploaded_reordered_featured_and_deleted(): void
    {
        Storage::fake('r2');
        $store = $this->makeStore('Media Store');
        app(CurrentStore::class)->set($store);
        $product = Product::query()->create([
            'title' => 'Nike Air Max',
            'slug' => 'nike-air-max-'.str()->random(8),
            'status' => ProductStatus::Draft,
        ]);
        $manager = app(ProductMediaManager::class);

        $first = $manager->upload($product, UploadedFile::fake()->create('first.jpg', 200, 'image/jpeg'), 'Nike Air Max ön görünüm');
        $second = $manager->upload($product, UploadedFile::fake()->create('second.webp', 200, 'image/webp'), 'Nike Air Max yan görünüm');
        $second = $manager->updateMetadata($product, $second, 'Güncellenmiş alt metin', true);
        $manager->reorder($product, [$second->id, $first->id]);

        $media = $product->media()->get();
        $this->assertTrue($second->is_featured);
        $this->assertSame('Güncellenmiş alt metin', $second->alt_text);
        $this->assertSame([$second->id, $first->id], $media->pluck('id')->all());
        Storage::disk('r2')->assertExists($first->storage_path);
        Storage::disk('r2')->assertExists($second->storage_path);

        $manager->delete($product, $second);

        Storage::disk('r2')->assertMissing($second->storage_path);
        $this->assertTrue($first->fresh()->is_featured);
        $this->assertSame(1, $product->media()->count());
    }

    private function makeStore(string $name): Store
    {
        $user = User::factory()->create();
        $merchant = Merchant::create(['owner_user_id' => $user->id]);

        return $merchant->stores()->create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->random(8),
        ]);
    }
}