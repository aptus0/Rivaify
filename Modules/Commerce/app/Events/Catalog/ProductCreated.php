<?php

namespace Modules\Commerce\Events\Catalog;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Commerce\Models\Catalog\Product;

class ProductCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Product $product) {}
}
