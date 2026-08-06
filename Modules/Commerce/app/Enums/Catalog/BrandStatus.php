<?php

namespace Modules\Commerce\Enums\Catalog;

enum BrandStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
