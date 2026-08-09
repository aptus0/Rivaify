<?php

namespace Modules\Ecosystem\DTOs;

use Carbon\CarbonImmutable;

final readonly class OAuthTokenResult
{
    public function __construct(
        public string $accessToken,
        public ?string $externalAccountId,
        public ?string $externalAccountName,
        public ?CarbonImmutable $expiresAt = null,
        public ?string $refreshToken = null,
        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {}
}
