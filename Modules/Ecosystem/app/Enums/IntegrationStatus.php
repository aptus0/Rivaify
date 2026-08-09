<?php

namespace Modules\Ecosystem\Enums;

/**
 * A store's connection state for one integration (store_integrations.status).
 */
enum IntegrationStatus: string
{
    case Pending = 'pending';
    case Connected = 'connected';
    case AttentionRequired = 'attention_required';
    case Error = 'error';
    case Disabled = 'disabled';
    case Disconnected = 'disconnected';
}
