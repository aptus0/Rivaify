<?php

namespace Modules\Commerce\Events\Catalog;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Commerce\Models\Catalog\Category;

class CategoryCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Category $category) {}
}
