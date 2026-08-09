<?php

namespace Modules\Commerce\Enums\Catalog;

enum CollectionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}

