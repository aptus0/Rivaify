<?php

namespace Modules\Commerce\Exceptions\Catalog;

use RuntimeException;

/**
 * Thrown when an action tries to attach a category/brand/etc. that doesn't
 * resolve within the current store's scope (brief §34: nested resources
 * must belong to the same store). Callers don't need to check the store_id
 * themselves — BelongsToStore's global scope already makes a cross-store
 * lookup resolve to "not found"; this exception just gives that a clear name.
 */
class CrossStoreAssignmentException extends RuntimeException {}
