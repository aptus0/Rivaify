<?php

namespace Modules\Commerce\Actions\Catalog;

use Modules\Commerce\Events\Catalog\ProductUpdated;
use Modules\Commerce\Exceptions\Catalog\CrossStoreAssignmentException;
use Modules\Commerce\Models\Catalog\Category;
use Modules\Commerce\Models\Catalog\Product;

class AssignProductCategory
{
    /**
     * Pass null to unassign. Assigning a category that doesn't resolve in
     * the current store (either it doesn't exist, or belongs to another
     * store — BelongsToStore's scope makes those indistinguishable, which
     * is exactly the point of brief §34) throws rather than silently
     * attaching a foreign row.
     */
    public function handle(Product $product, ?int $categoryId): Product
    {
        if ($categoryId !== null && Category::query()->find($categoryId) === null) {
            throw new CrossStoreAssignmentException(
                "Category #{$categoryId} does not exist in the current store."
            );
        }

        $product->update(['category_id' => $categoryId]);

        ProductUpdated::dispatch($product);

        return $product;
    }
}
