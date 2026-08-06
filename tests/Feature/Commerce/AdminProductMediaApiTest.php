<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Catalog\ProductMedia;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreUser;
use Tests\TestCase;

class AdminProductMediaApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');
        $this->withHeader('Referer', 'https://app.rivaify.com');
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_owner_can_upload_reorder_feature_and_stream_product_media(): void
    {
        [$owner, $store] = $this->makeStoreWithOwner('Media API Store');
        app(CurrentStore::class)->set($store);
        $product = Product::query()->create([
            'title' => 'Nike Air Max',
            'slug' => 'nike-air-max-'.str()->random(8),
            'status' => ProductStatus::Draft,
        ]);
        app(CurrentStore::class)->clear();
        Sanctum::actingAs($owner);

        $first = $this->withSession(['current_store_id' => $store->id])
            ->post("/api/v1/products/{$product->ulid}/media", [
                'file' => UploadedFile::fake()->create('first.jpg', 200, 'image/jpeg'),
                'alt_text' => 'Ön görünüm',
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data');
        $second = $this->withSession(['current_store_id' => $store->id])
            ->post("/api/v1/products/{$product->ulid}/media", [
                'file' => UploadedFile::fake()->create('second.webp', 200, 'image/webp'),
                'alt_text' => 'Yan görünüm',
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data');

        $this->assertTrue($first['is_featured']);
        $this->assertFalse($second['is_featured']);

        $this->withSession(['current_store_id' => $store->id])
            ->patchJson("/api/v1/products/{$product->ulid}/media/{$second['id']}", [
                'alt_text' => 'Güncellenmiş alt metin',
                'is_featured' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_featured', true);
        $this->withSession(['current_store_id' => $store->id])
            ->postJson("/api/v1/products/{$product->ulid}/media/reorder", [
                'media_ids' => [$second['id'], $first['id']],
            ])
            ->assertOk()
            ->assertJsonPath('data.media.0.id', $second['id']);

        $record = ProductMedia::query()->where('ulid', $second['id'])->firstOrFail();
        Storage::disk('r2')->assertExists($record->storage_path);
        $this->withSession(['current_store_id' => $store->id])
            ->get($second['url'])
            ->assertOk();
        $this->withSession(['current_store_id' => $store->id])
            ->deleteJson("/api/v1/products/{$product->ulid}/media/{$second['id']}")
            ->assertNoContent();

        Storage::disk('r2')->assertMissing($record->storage_path);
        $this->assertTrue(ProductMedia::query()->where('ulid', $first['id'])->firstOrFail()->is_featured);
    }

    public function test_owner_cannot_access_media_for_a_product_in_another_store(): void
    {
        [$firstOwner, $firstStore] = $this->makeStoreWithOwner('First Media Store');
        [$secondOwner, $secondStore] = $this->makeStoreWithOwner('Second Media Store');
        app(CurrentStore::class)->set($firstStore);
        $product = Product::query()->create([
            'title' => 'Private Product',
            'slug' => 'private-product-'.str()->random(8),
            'status' => ProductStatus::Draft,
        ]);
        app(CurrentStore::class)->clear();
        Sanctum::actingAs($firstOwner);
        $media = $this->withSession(['current_store_id' => $firstStore->id])
            ->post("/api/v1/products/{$product->ulid}/media", [
                'file' => UploadedFile::fake()->create('private.jpg', 200, 'image/jpeg'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data');

        Sanctum::actingAs($secondOwner);
        $this->withSession(['current_store_id' => $secondStore->id])
            ->get($media['url'])
            ->assertNotFound();
        $this->withSession(['current_store_id' => $secondStore->id])
            ->post("/api/v1/products/{$product->ulid}/media", [
                'file' => UploadedFile::fake()->create('blocked.jpg', 200, 'image/jpeg'),
            ], ['Accept' => 'application/json'])
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function makeStoreWithOwner(string $name): array
    {
        $owner = User::factory()->create();
        $merchant = Merchant::create(['owner_user_id' => $owner->id]);
        $store = $merchant->stores()->create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->random(8),
        ]);
        StoreUser::withoutGlobalScope(StoreScope::class)->create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'role' => StoreUserRole::Owner,
            'status' => StoreUserStatus::Active,
            'joined_at' => now(),
        ]);

        return [$owner, $store];
    }
}