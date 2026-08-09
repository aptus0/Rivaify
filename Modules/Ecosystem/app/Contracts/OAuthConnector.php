<?php

namespace Modules\Ecosystem\Contracts;

use Modules\Ecosystem\DTOs\OAuthTokenResult;

/**
 * Connectors whose "Bağla" flow is an OAuth2 authorization-code redirect
 * (Meta, TikTok, ...) rather than a plain credential form (PayTR-style
 * merchant id/key/salt). The `$state` value is an opaque, single-use
 * token minted by OAuthStateStore — connectors never need to know what's
 * inside it.
 */
interface OAuthConnector
{
    public function authorizationUrl(string $state): string;

    public function handleCallback(string $code): OAuthTokenResult;
}
