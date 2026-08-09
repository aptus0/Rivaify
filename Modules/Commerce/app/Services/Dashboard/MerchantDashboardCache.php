<?php

namespace Modules\Commerce\Services\Dashboard;

use Closure;
use Illuminate\Support\Facades\Cache;
use Modules\Commerce\Enums\Dashboard\MerchantDashboardAudience;

class MerchantDashboardCache
{
    public const TTL_SECONDS = 60;

    /** @var list<string> */
    private const RANGES = ['today', '7d', '30d'];

    /**
     * @param  Closure(): array<string, mixed>  $resolver
     * @return array<string, mixed>
     */
    public function remember(
        int $storeId,
        string $range,
        MerchantDashboardAudience $audience,
        Closure $resolver,
    ): array
    {
        return Cache::remember($this->key($storeId, $range, $audience), self::TTL_SECONDS, $resolver);
    }

    public function forgetStore(int $storeId): void
    {
        foreach (self::RANGES as $range) {
            foreach (MerchantDashboardAudience::cases() as $audience) {
                Cache::forget($this->key($storeId, $range, $audience));
            }
        }
    }

    public function key(int $storeId, string $range, MerchantDashboardAudience $audience): string
    {
        return "commerce:dashboard:v2:store:{$storeId}:audience:{$audience->value}:range:{$range}";
    }
}
