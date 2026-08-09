<?php

namespace Modules\Ecosystem\Connectors\Social;

use App\Core\Tenancy\CurrentStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Catalog\ProductVariant;
use Modules\Ecosystem\Contracts\OAuthConnector;
use Modules\Ecosystem\Contracts\SocialCommerceConnector;
use Modules\Ecosystem\DTOs\HealthCheckResult;
use Modules\Ecosystem\DTOs\OAuthTokenResult;
use Modules\Ecosystem\Exceptions\IntegrationNotAvailableException;
use Modules\Ecosystem\Models\StoreIntegration;
use Modules\Ecosystem\Services\IntegrationActivityLogger;
use Modules\Ecosystem\Services\IntegrationSecretStore;

/**
 * Backs both the `facebook` and `instagram` registry entries: a merchant
 * connects once through Facebook Login for Business, which (via the
 * linked Page) also carries Instagram Business Account access — Meta's
 * Graph API treats them as one asset graph, not two separate logins.
 *
 * Catalog/inventory push uses the Commerce Catalog Batch API, which only
 * needs the `catalog_management` permission. Order import is
 * deliberately NOT implemented here: it requires Meta Shop Checkout
 * eligibility (a separate Business Manager approval this account doesn't
 * have), so it isn't listed as a capability in the registry either —
 * advertising it would be a capability the connector can't actually
 * deliver.
 */
class MetaConnector implements OAuthConnector, SocialCommerceConnector
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly IntegrationSecretStore $secrets,
        private readonly IntegrationActivityLogger $activity,
    ) {}

    public function authorizationUrl(string $state): string
    {
        $params = [
            'client_id' => $this->appId(),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'response_type' => 'code',
            'scope' => implode(',', [
                'pages_show_list',
                'pages_read_engagement',
                'catalog_management',
                'instagram_basic',
                'business_management',
            ]),
        ];

        return "https://www.facebook.com/{$this->apiVersion()}/dialog/oauth?".http_build_query($params);
    }

    public function handleCallback(string $code): OAuthTokenResult
    {
        $exchanged = Http::get($this->graphUrl('oauth/access_token'), [
            'client_id' => $this->appId(),
            'client_secret' => $this->appSecret(),
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
        ])->throw()->json();

        $longLived = Http::get($this->graphUrl('oauth/access_token'), [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->appId(),
            'client_secret' => $this->appSecret(),
            'fb_exchange_token' => $exchanged['access_token'],
        ])->throw()->json();
        $accessToken = $longLived['access_token'] ?? $exchanged['access_token'];

        $pages = Http::get($this->graphUrl('me/accounts'), [
            'fields' => 'id,name,access_token,instagram_business_account',
            'access_token' => $accessToken,
        ])->throw()->json('data', []);
        $page = $pages[0] ?? null;
        if ($page === null) {
            throw new IntegrationNotAvailableException('Bu hesaba bağlı bir Facebook Sayfası bulunamadı.');
        }

        return new OAuthTokenResult(
            accessToken: $accessToken,
            externalAccountId: $page['id'],
            externalAccountName: $page['name'] ?? null,
            expiresAt: isset($longLived['expires_in']) ? now()->addSeconds((int) $longLived['expires_in'])->toImmutable() : null,
            metadata: [
                'page_id' => $page['id'],
                'page_access_token' => $page['access_token'] ?? null,
                'instagram_business_account_id' => $page['instagram_business_account']['id'] ?? null,
            ],
        );
    }

    public function disconnect(StoreIntegration $integration): void
    {
        $token = $this->secrets->get($integration, 'access_token');
        if ($token !== null) {
            Http::delete($this->graphUrl('me/permissions'), ['access_token' => $token]);
        }
    }

    public function healthCheck(StoreIntegration $integration): HealthCheckResult
    {
        $token = $this->secrets->get($integration, 'access_token');
        if ($token === null) {
            return new HealthCheckResult(false, 'Erişim anahtarı bulunamadı.');
        }

        try {
            $me = Http::get($this->graphUrl('me'), ['fields' => 'id,name', 'access_token' => $token])->throw()->json();

            return new HealthCheckResult(true, 'Bağlantı sağlıklı.', ['account_name' => $me['name'] ?? null]);
        } catch (\Throwable $exception) {
            return new HealthCheckResult(false, 'Meta API bağlantı hatası: '.$exception->getMessage());
        }
    }

    public function refreshCredentials(StoreIntegration $integration): void
    {
        $token = $this->secrets->get($integration, 'access_token');
        if ($token === null) {
            throw new IntegrationNotAvailableException('Yenilenecek bir erişim anahtarı yok.');
        }
        $response = Http::get($this->graphUrl('oauth/access_token'), [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->appId(),
            'client_secret' => $this->appSecret(),
            'fb_exchange_token' => $token,
        ])->throw()->json();
        $this->secrets->store($integration, array_merge($integration->credentials ?? [], [
            'access_token' => $response['access_token'] ?? $token,
        ]));
    }

    public function pushProduct(StoreIntegration $integration, Product $product): void
    {
        $catalogId = $integration->configuration['catalog_id'] ?? null;
        if ($catalogId === null) {
            throw new IntegrationNotAvailableException('Meta Commerce Catalog ID henüz yapılandırılmadı.');
        }
        $token = $this->secrets->get($integration, 'access_token');
        $store = $this->currentStore->store();
        $featuredImage = $product->media()->first();
        $imageUrl = $featuredImage !== null ? Storage::disk($featuredImage->storage_disk)->url($featuredImage->storage_path) : null;

        $requests = $product->variants->map(fn (ProductVariant $variant): array => [
            'method' => 'UPDATE',
            'data' => [
                'id' => $variant->ulid,
                'title' => $product->title,
                'description' => strip_tags((string) $product->description),
                'availability' => $variant->status->value === 'active' ? 'in stock' : 'out of stock',
                'condition' => 'new',
                'price' => (string) round(((float) $variant->price) * 100).' '.$store->default_currency,
                'link' => "https://{$store->slug}.rivaify.com/products/{$product->slug}",
                'image_link' => $imageUrl,
            ],
        ])->values()->all();

        Http::asForm()->post($this->graphUrl("{$catalogId}/items_batch"), [
            'access_token' => $token,
            'item_type' => 'PRODUCT_ITEM',
            'requests' => json_encode($requests),
        ])->throw();
    }

    public function updateInventory(StoreIntegration $integration, ProductVariant $variant): void
    {
        $catalogId = $integration->configuration['catalog_id'] ?? null;
        if ($catalogId === null) {
            throw new IntegrationNotAvailableException('Meta Commerce Catalog ID henüz yapılandırılmadı.');
        }
        $token = $this->secrets->get($integration, 'access_token');
        $available = (int) DB::table('inventory_levels')
            ->where('store_id', $variant->store_id)
            ->whereIn('inventory_item_id', function ($query) use ($variant) {
                $query->select('id')->from('inventory_items')->where('inventory_items.product_variant_id', $variant->id);
            })
            ->sum(DB::raw('available_quantity - reserved_quantity'));

        Http::asForm()->post($this->graphUrl("{$catalogId}/items_batch"), [
            'access_token' => $token,
            'item_type' => 'PRODUCT_ITEM',
            'requests' => json_encode([[
                'method' => 'UPDATE',
                'data' => [
                    'id' => $variant->ulid,
                    'availability' => $available > 0 ? 'in stock' : 'out of stock',
                    'inventory' => max($available, 0),
                ],
            ]]),
        ])->throw();
    }

    public function handleWebhook(StoreIntegration $integration, array $payload): void
    {
        $fields = collect($payload['entry'] ?? [])
            ->flatMap(fn (array $entry): array => $entry['changes'] ?? [])
            ->pluck('field')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->activity->record($integration, 'webhook_received', 'Meta üzerinden bir webhook olayı alındı.', [
            'fields' => $fields,
        ]);
    }

    public function verifySignature(string $rawBody, ?string $signatureHeader): bool
    {
        if ($signatureHeader === null || ! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }
        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $this->appSecret());

        return hash_equals($expected, $signatureHeader);
    }

    private function appId(): string
    {
        return (string) config('ecosystem.connectors.meta.app_id');
    }

    private function appSecret(): string
    {
        return (string) config('ecosystem.connectors.meta.app_secret');
    }

    private function redirectUri(): string
    {
        return (string) config('ecosystem.connectors.meta.redirect_uri');
    }

    private function apiVersion(): string
    {
        return (string) config('ecosystem.connectors.meta.api_version', 'v21.0');
    }

    private function graphUrl(string $path): string
    {
        return "https://graph.facebook.com/{$this->apiVersion()}/{$path}";
    }
}
