<?php

namespace Modules\Commerce\Enums\Catalog;

enum ProductType: string
{
    case Physical = 'physical';
    case Digital = 'digital';
    case Service = 'service';
}
