<?php

namespace Modules\Ecosystem\Registry;

use Modules\Ecosystem\Enums\IntegrationAvailability;
use Modules\Ecosystem\Enums\IntegrationCategory;

final readonly class IntegrationDefinition
{
    /**
     * @param  string[]  $capabilities  Only API features actually implemented by the connector —
     *                                  never listed here just because the provider's API supports them.
     * @param  class-string|null  $connectorClass  Null when there is no working connector yet (coming_soon/planned).
     */
    public function __construct(
        public string $key,
        public string $name,
        public IntegrationCategory $category,
        public string $description,
        public string $logo,
        public IntegrationAvailability $availability,
        public array $capabilities,
        public string $authenticationType,
        public ?string $connectorClass = null,
        public ?string $documentationUrl = null,
    ) {}
}
