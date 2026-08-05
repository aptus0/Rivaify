<?php

namespace Modules\Commerce\Enums\Catalog;

/**
 * Shared by products and variants. Archived is a lifecycle state, not a
 * delete — `deleted_at` is the real soft-delete (brief §3: "Archived ≠
 * Deleted").
 */
enum ProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
