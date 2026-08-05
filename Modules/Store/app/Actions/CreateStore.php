<?php

namespace Modules\Store\Actions;

use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Merchant\Events\MerchantCreated;
use Modules\Merchant\Models\Merchant;
use Modules\Store\DTOs\CreateStoreData;
use Modules\Store\Enums\OnboardingStatus;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Events\StoreCreated;
use Modules\Store\Exceptions\MerchantAlreadyHasStoreException;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreUser;

/**
 * Find-or-create the user's Merchant, then create their (one, for now —
 * see MerchantAlreadyHasStoreException) Store and owner membership.
 * Runs before any store context exists, so StoreUser is written with an
 * explicit store_id via a deliberate scope bypass (see StoreScope's
 * docblock) rather than relying on CurrentStore.
 */
class CreateStore
{
    public function handle(User $user, CreateStoreData $data): Store
    {
        return DB::transaction(function () use ($user, $data) {
            $merchant = Merchant::query()->firstOrCreate(['owner_user_id' => $user->id]);
            if ($merchant->wasRecentlyCreated) {
                MerchantCreated::dispatch($merchant);
            }

            if ($merchant->stores()->exists()) {
                throw new MerchantAlreadyHasStoreException(
                    "Merchant #{$merchant->id} already has a store — multi-store merchants aren't supported yet."
                );
            }

            $store = $merchant->stores()->create([
                'name' => $data->name,
                'slug' => $this->uniqueSlug($data->name),
                'default_currency' => $data->defaultCurrency,
                'default_locale' => $data->defaultLocale,
                'timezone' => $data->timezone,
                'country_code' => $data->countryCode,
                'onboarding_status' => OnboardingStatus::BusinessInformation,
            ]);

            StoreUser::withoutGlobalScope(StoreScope::class)->create([
                'store_id' => $store->id,
                'user_id' => $user->id,
                'role' => StoreUserRole::Owner,
                'status' => StoreUserStatus::Active,
                'joined_at' => now(),
            ]);

            StoreCreated::dispatch($store);

            return $store;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Store::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
