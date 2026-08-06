<?php

namespace Modules\Commerce\DTOs\Catalog;

readonly class CreateCategoryData
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?int $parentId = null,
        public int $position = 0,
    ) {}
}
