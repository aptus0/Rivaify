<?php

namespace Modules\Store\Enums;

enum StoreUserRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Manager = 'manager';
    case Staff = 'staff';
    case Support = 'support';
    case Developer = 'developer';
}
