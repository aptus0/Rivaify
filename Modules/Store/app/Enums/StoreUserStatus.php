<?php

namespace Modules\Store\Enums;

enum StoreUserStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Suspended = 'suspended';
    case Removed = 'removed';
}
