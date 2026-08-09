<?php

namespace Modules\Ecosystem\DTOs;

final readonly class HealthCheckResult
{
    public function __construct(
        public bool $healthy,
        public string $message,
        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {}
}
