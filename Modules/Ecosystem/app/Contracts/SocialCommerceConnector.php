<?php

namespace Modules\Ecosystem\Contracts;

use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Catalog\ProductVariant;
use Modules\Ecosystem\Models\StoreIntegration;

interface SocialCommerceConnector extends IntegrationConnector
{
    public function pushProduct(StoreIntegration $integration, Product $product): void;

    public function updateInventory(StoreIntegration $integration, ProductVariant $variant): void;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(StoreIntegration $integration, array $payload): void;
}
