<?php

namespace Modules\Commerce\Actions\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Commerce\DTOs\Catalog\CreateBrandData;
use Modules\Commerce\Models\Catalog\Brand;

class CreateBrand
{
    public function handle(CreateBrandData $data): Brand
    {
        return DB::transaction(fn () => Brand::query()->create([
            'name' => $data->name,
            'slug' => $this->uniqueSlug($data->name),
            'description' => $data->description,
        ]));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Brand::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
