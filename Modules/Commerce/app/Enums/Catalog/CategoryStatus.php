<?php

namespace Modules\Commerce\Enums\Catalog;

enum CategoryStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
