<?php

namespace Modules\Ecosystem\Enums;

enum IntegrationWebhookStatus: string
{
    case Received = 'received';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
    case Ignored = 'ignored';
}
