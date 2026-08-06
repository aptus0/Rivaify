<?php

namespace Modules\Commerce\Actions\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Commerce\DTOs\Catalog\CreateCategoryData;
use Modules\Commerce\Events\Catalog\CategoryCreated;
use Modules\Commerce\Exceptions\Catalog\CrossStoreAssignmentException;
use Modules\Commerce\Models\Catalog\Category;

class CreateCategory
{
    public function handle(CreateCategoryData $data): Category
    {
        return DB::transaction(function () use ($data) {
            // Category::find() is scoped by BelongsToStore's global scope, so a
            // parent_id belonging to another store simply won't resolve here —
            // that's the tenant-isolation guarantee from brief §34 in practice.
            if ($data->parentId !== null && Category::query()->find($data->parentId) === null) {
                throw new CrossStoreAssignmentException(
                    "Parent category #{$data->parentId} does not exist in the current store."
                );
            }

            $category = Category::query()->create([
                'parent_id' => $data->parentId,
                'name' => $data->name,
                'slug' => $this->uniqueSlug($data->name),
                'description' => $data->description,
                'position' => $data->position,
            ]);

            CategoryCreated::dispatch($category);

            return $category;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Category::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
