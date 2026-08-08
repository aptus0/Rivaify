<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Models\Store;
use Tests\TestCase;

class StorefrontApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_slug_host_resolves_storefront_and_persists_guest_cart_in_http_only_cookie(): void
    {
        $store = $this->makeStore('Yasemin Giyim');
        app(CurrentStore::class)->set($store);
        $product = Product::query()->create([
            'title' => 'Nike Air Max',
            'slug' => 'nike-air-max',
            'status' => ProductStatus::Active,
        ]);
        $variant = $product->variants()->create([
            'title' => 'Black / 42',
            'price' => '4499.00',
            'status' => ProductStatus::Active,
        ]);
        app(CurrentStore::class)->clear();

        $response = $this->postJson($this->storefrontUrl($store, '/api/storefront/v1/cart/items'), [
                'variant_id' => $variant->ulid,
                'quantity' => 2,
                'price' => '1.00',
            ]);

        $response
            ->assertOk()
            ->assertCookie('rivaify_cart')
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.unit_price', '4499.00')
            ->assertJsonPath('data.grand_total', '8998.00');

        $cart = Cart::query()->sole();
        $this->withCredentials()
            ->withUnencryptedCookie('rivaify_cart', $cart->token)
            ->getJson($this->storefrontUrl($store, '/api/storefront/v1/cart'))
            ->assertOk()
            ->assertJsonPath('data.id', $cart->ulid)
            ->assertJsonPath('data.items.0.quantity', 2);
    }

    public function test_unknown_storefront_host_cannot_access_a_store(): void
    {
        $this->getJson('http://unknown.rivaify.test/api/storefront/v1/store')
            ->assertNotFound()
            ->assertJsonPath('message', 'store_not_found');
    }

    public function test_inactive_variant_cannot_be_added_through_storefront_api(): void
    {
        $store = $this->makeStore('Inactive Store');
        app(CurrentStore::class)->set($store);
        $product = Product::query()->create([
            'title' => 'Draft Product',
            'slug' => 'draft-product',
            'status' => ProductStatus::Active,
        ]);
        $variant = $product->variants()->create([
            'title' => 'Default',
            'price' => '99.00',
            'status' => ProductStatus::Draft,
        ]);
        app(CurrentStore::class)->clear();

        $this->postJson($this->storefrontUrl($store, '/api/storefront/v1/cart/items'), [
                'variant_id' => $variant->ulid,
                'quantity' => 1,
            ])
            ->assertStatus(422);
    }

    public function test_store_subdomain_renders_the_storefront_shell_for_tokenized_checkout_urls(): void
    {
        $store = $this->makeStore('Checkout Shell Store');

        $this->get("http://{$store->slug}.rivaify.test/checkouts/01KZ0000000000000000000000")
            ->assertOk()
            ->assertSee('id="root"', false)
            ->assertSee('Rivaify Store');
    }

    private function makeStore(string $name): Store
    {
        $user = User::factory()->create();
        $merchant = Merchant::create(['owner_user_id' => $user->id]);

        return $merchant->stores()->create([
            'name' => $name,
            'slug' => str($name)->slug(),
        ]);
    }

    private function storefrontUrl(Store $store, string $path): string
    {
        return "http://{$store->slug}.rivaify.test{$path}";
    }
}