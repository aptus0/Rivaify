<?php

namespace Modules\Commerce\Services\Storefront;

use Illuminate\Database\Eloquent\Builder;
use Modules\Commerce\Enums\Catalog\ProductStatus;

class ProductStorefrontEligibility
{
    /**
     * @param  Builder<\Modules\Commerce\Models\Catalog\Product>  $query
     * @return Builder<\Modules\Commerce\Models\Catalog\Product>
     */
    public function apply(Builder $query): Builder
    {
        return $query
            ->where('status', ProductStatus::Active->value)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->whereHas('variants', fn (Builder $variant): Builder => $variant->where('status', ProductStatus::Active->value));
    }
}
