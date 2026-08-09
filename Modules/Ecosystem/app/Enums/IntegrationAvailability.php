<?php

namespace Modules\Ecosystem\Enums;

/**
 * Whether an integration in the registry can actually be connected right
 * now. Driven by whether a real connector class + credentials exist for
 * it — never hardcoded true in the UI (brief §3: "statüler gerçek
 * configuration'dan gelmeli").
 */
enum IntegrationAvailability: string
{
    case Available = 'available';
    case Beta = 'beta';
    case ComingSoon = 'coming_soon';
    case Planned = 'planned';
}
