<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Commerce\Enums\Shipping\ShippingMethodStatus;
use Modules\Commerce\Enums\Tax\TaxRateStatus;
use Modules\Commerce\Models\Inventory\InventoryLocation;
use Modules\Commerce\Models\Shipping\ShippingMethod;
use Modules\Commerce\Models\Tax\TaxRate;
use Modules\Commerce\Services\Store\StoreDomainVerifier;
use Modules\Store\Enums\StoreStatus;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreDomain;
use Modules\Store\Models\StoreUser;

class AdminSettingsController extends Controller
{
    public function show(Request $request, CurrentStore $currentStore): JsonResponse
    {
        $store = $currentStore->store()->load(['domains' => fn ($query) => $query
            ->orderByDesc('is_primary')
            ->orderByDesc('verified_at')
            ->orderBy('domain')]);

        return response()->json(['data' => $this->present($store, $request->user())]);
    }

    public function integrations(CurrentStore $currentStore): JsonResponse
    {
        $store = $currentStore->store()->load(['domains' => fn ($query) => $query
            ->orderByDesc('is_primary')
            ->orderByDesc('verified_at')]);
        $primaryDomain = $store->domains->firstWhere('is_primary', true);
        $verifiedDomainCount = $store->domains->whereNotNull('verified_at')->count();
        $payment = $this->paytrStatus();
        $activeShippingMethods = ShippingMethod::query()->where('status', ShippingMethodStatus::Active->value)->count();
        $activeInventoryLocations = InventoryLocation::query()->where('is_active', true)->count();
        $storefrontActive = $store->status === StoreStatus::Active && $verifiedDomainCount > 0;

        return response()->json(['data' => [
            'channels' => [
                [
                    'id' => 'online_store',
                    'name' => 'Online Mağaza',
                    'status' => $storefrontActive ? 'active' : 'needs_attention',
                    'available' => true,
                    'description' => $storefrontActive
                        ? 'Mağaza doğrulanmış alan adı üzerinden yayında.'
                        : 'Yayın için mağaza aktif ve en az bir alan adı doğrulanmış olmalıdır.',
                    'detail' => $primaryDomain?->domain,
                    'manage_path' => '/settings',
                ],
                [
                    'id' => 'instagram',
                    'name' => 'Instagram Shopping',
                    'status' => 'not_available',
                    'available' => false,
                    'description' => 'Meta katalog bağlantısı bu sürümde desteklenmiyor.',
                    'detail' => null,
                    'manage_path' => null,
                ],
                [
                    'id' => 'tiktok',
                    'name' => 'TikTok Shop',
                    'status' => 'not_available',
                    'available' => false,
                    'description' => 'TikTok Shop bağlantısı bu sürümde desteklenmiyor.',
                    'detail' => null,
                    'manage_path' => null,
                ],
            ],
            'apps' => [
                [
                    'id' => 'paytr',
                    'name' => 'PayTR Ödeme',
                    'status' => $payment['enabled']
                        ? ($payment['test_mode'] ? 'test_mode' : 'active')
                        : ($payment['configured'] ? 'needs_attention' : 'not_configured'),
                    'available' => true,
                    'description' => $payment['enabled']
                        ? 'PayTR ile ödeme alma akışı hazır.'
                        : ($payment['configured']
                            ? 'PayTR bağlantısı hazırlanmış, ödeme sağlayıcı seçimi bekliyor.'
                            : 'Ödeme alma akışı henüz kullanıma hazır değil.'),
                    'detail' => $payment['configured'] ? ($payment['test_mode'] ? 'Test modu' : 'Canlı mod') : null,
                    'manage_path' => null,
                ],
                [
                    'id' => 'shipping',
                    'name' => 'Rivaify Kargo',
                    'status' => $activeShippingMethods > 0 ? 'active' : 'not_configured',
                    'available' => true,
                    'description' => $activeShippingMethods > 0
                        ? 'Checkout için aktif kargo yöntemleri hazır.'
                        : 'Checkout açılmadan önce en az bir kargo yöntemi oluşturulmalı.',
                    'detail' => $activeShippingMethods > 0 ? "{$activeShippingMethods} aktif yöntem" : null,
                    'manage_path' => null,
                ],
                [
                    'id' => 'inventory',
                    'name' => 'Rivaify Envanter',
                    'status' => $activeInventoryLocations > 0 ? 'active' : 'not_configured',
                    'available' => true,
                    'description' => $activeInventoryLocations > 0
                        ? 'Stok takibi için aktif lokasyonlar hazır.'
                        : 'Stok takibi için en az bir envanter lokasyonu oluşturulmalı.',
                    'detail' => $activeInventoryLocations > 0 ? "{$activeInventoryLocations} aktif lokasyon" : null,
                    'manage_path' => '/inventory',
                ],
            ],
        ]]);
    }

    public function updateStore(Request $request, CurrentStore $currentStore): JsonResponse
    {
        $request->merge([
            ...($request->has('name') ? ['name' => trim((string) $request->input('name'))] : []),
            ...($request->has('default_currency') ? ['default_currency' => mb_strtoupper((string) $request->input('default_currency'))] : []),
            ...($request->has('default_locale') ? ['default_locale' => mb_strtolower((string) $request->input('default_locale'))] : []),
            ...($request->has('country_code') ? ['country_code' => mb_strtoupper((string) $request->input('country_code'))] : []),
        ]);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'default_currency' => ['sometimes', 'string', Rule::in(['TRY', 'USD', 'EUR', 'GBP'])],
            'default_locale' => ['sometimes', 'string', Rule::in(['tr', 'en'])],
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            'country_code' => ['sometimes', 'string', 'size:2', 'alpha'],
        ]);

        $store = DB::transaction(function () use ($validated, $currentStore): Store {
            $store = Store::query()->lockForUpdate()->findOrFail($currentStore->id());
            $nextCountryCode = $validated['country_code'] ?? $store->country_code;
            if ($nextCountryCode !== $store->country_code) {
                $this->ensureActiveDefaultTaxRate($nextCountryCode);
            }
            $store->update($validated);

            return $store;
        });
        $store->load(['domains' => fn ($query) => $query
            ->orderByDesc('is_primary')
            ->orderByDesc('verified_at')
            ->orderBy('domain')]);

        return response()->json(['data' => $this->present($store, $request->user())]);
    }

    public function storeDomain(Request $request, CurrentStore $currentStore): JsonResponse
    {
        $request->merge(['domain' => $this->normalizeDomain((string) $request->input('domain', ''))]);
        $validated = $request->validate([
            'domain' => [
                'required',
                'string',
                'max:253',
                Rule::unique('store_domains', 'domain'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $domain = (string) $value;
                    if (! $this->isValidCustomDomain($domain)) {
                        $fail('Geçerli bir özel alan adı girin (ör. magazam.com).');
                    }
                    if (str_ends_with($domain, '.rivaify.com') || $domain === 'rivaify.com') {
                        $fail('Rivaify alan adları özel alan adı olarak eklenemez.');
                    }
                },
            ],
        ]);

        $domain = $currentStore->store()->domains()->create([
            'domain' => $validated['domain'],
            'is_primary' => false,
            // Domain ownership must be proven by a separate DNS verification
            // process. Never mark a merchant-provided hostname as verified here.
            'verified_at' => null,
        ]);

        return response()->json(['data' => $this->presentDomain($domain)], 201);
    }

    public function destroyDomain(string $ulid): JsonResponse
    {
        $domain = StoreDomain::query()->where('ulid', $ulid)->firstOrFail();
        if ($domain->is_primary) {
            return response()->json(['message' => 'primary_domain_cannot_be_deleted'], 422);
        }

        $domain->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function verifyDomain(string $ulid, CurrentStore $currentStore, StoreDomainVerifier $verifier): JsonResponse
    {
        $domain = StoreDomain::query()->where('ulid', $ulid)->firstOrFail();
        $store = $currentStore->store();
        if (! $verifier->verify($domain, $store)) {
            return response()->json([
                'message' => 'domain_dns_verification_failed',
                'verification' => $this->domainVerification($domain, $store, $verifier),
            ], 422);
        }

        return response()->json(['data' => $this->presentDomain($domain->refresh())]);
    }

    public function makePrimaryDomain(string $ulid, CurrentStore $currentStore): JsonResponse
    {
        $domain = StoreDomain::query()->where('ulid', $ulid)->firstOrFail();
        if ($domain->verified_at === null) {
            return response()->json(['message' => 'domain_must_be_verified'], 422);
        }

        $store = $currentStore->store();
        DB::transaction(function () use ($store, $domain): void {
            $store->domains()->lockForUpdate()->update(['is_primary' => false]);
            $domain->forceFill(['is_primary' => true])->save();
        });

        return response()->json(['data' => $this->presentDomain($domain->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Store $store, ?User $user): array
    {
        return [
            'store' => [
                'id' => $store->ulid,
                'name' => $store->name,
                'slug' => $store->slug,
                'status' => $store->status->value,
                'onboarding_status' => $store->onboarding_status->value,
                'default_currency' => $store->default_currency,
                'default_locale' => $store->default_locale,
                'timezone' => $store->timezone,
                'country_code' => $store->country_code,
            ],
            'domains' => $store->domains->map(fn (StoreDomain $domain): array => $this->presentDomain($domain))->values(),
            'payments' => [
                'default_provider' => (string) config('commerce.payments.default', 'paytr'),
                'paytr' => $this->paytrStatus(),
            ],
            'permissions' => [
                'can_manage' => $this->canManageSettings($store, $user),
            ],
        ];
    }

    private function canManageSettings(Store $store, ?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return StoreUser::query()
            ->where('store_id', $store->id)
            ->where('user_id', $user->id)
            ->where('status', StoreUserStatus::Active->value)
            ->whereIn('role', [StoreUserRole::Owner->value, StoreUserRole::Admin->value])
            ->exists();
    }

    private function ensureActiveDefaultTaxRate(string $countryCode): void
    {
        if (TaxRate::query()
            ->whereRaw('upper(country_code) = ?', [mb_strtoupper($countryCode)])
            ->where('status', TaxRateStatus::Active->value)
            ->exists()) {
            return;
        }

        $defaults = $countryCode === 'TR'
            ? ['name' => 'KDV %20 (Fiyata Dahil)', 'rate' => '20.00', 'is_inclusive' => true]
            : ['name' => 'Varsayılan Vergi %0', 'rate' => '0.00', 'is_inclusive' => false];
        $rate = TaxRate::query()->firstOrCreate(
            ['name' => $defaults['name'], 'country_code' => $countryCode],
            ['rate' => $defaults['rate'], 'is_inclusive' => $defaults['is_inclusive']],
        );
        if ($rate->status !== TaxRateStatus::Active) {
            $rate->update(['status' => TaxRateStatus::Active]);
        }
    }

    /**
     * Public, non-secret PayTR readiness data. Merchant credentials must
     * never be added to this payload, even in masked form.
     *
     * @return array{configured: bool, enabled: bool, test_mode: bool, installments_enabled: bool, max_installment: int, callback_url: string}
     */
    private function paytrStatus(): array
    {
        $paytr = (array) config('commerce.payments.paytr', []);
        $configured = collect(['merchant_id', 'merchant_key', 'merchant_salt'])
            ->every(fn (string $key): bool => trim((string) ($paytr[$key] ?? '')) !== '');
        $defaultProvider = (string) config('commerce.payments.default', 'paytr');

        return [
            'configured' => $configured,
            'enabled' => $defaultProvider === 'paytr' && $configured,
            'test_mode' => (bool) ($paytr['test_mode'] ?? true),
            'installments_enabled' => ! (bool) ($paytr['no_installment'] ?? false),
            'max_installment' => (int) ($paytr['max_installment'] ?? 0),
            'callback_url' => url('/webhooks/payments/paytr'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentDomain(StoreDomain $domain): array
    {
        return [
            'id' => $domain->ulid,
            'domain' => $domain->domain,
            'is_primary' => $domain->is_primary,
            'verified' => $domain->verified_at !== null,
            'verified_at' => $domain->verified_at?->toIso8601String(),
            'created_at' => $domain->created_at?->toIso8601String(),
        ];
    }

    /** @return array{cname_target: string, txt_host: string, txt_value: string} */
    private function domainVerification(StoreDomain $domain, Store $store, StoreDomainVerifier $verifier): array
    {
        return [
            'cname_target' => "{$store->slug}.rivaify.com",
            'txt_host' => '_rivaify-verification.'.$domain->domain,
            'txt_value' => $verifier->txtValue($store),
        ];
    }

    private function normalizeDomain(string $domain): string
    {
        return mb_strtolower(rtrim(trim($domain), '.'));
    }

    private function isValidCustomDomain(string $domain): bool
    {
        return str_contains($domain, '.')
            && filter_var($domain, FILTER_VALIDATE_IP) === false
            && filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }
}
