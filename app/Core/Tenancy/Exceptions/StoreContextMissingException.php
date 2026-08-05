<?php

namespace App\Core\Tenancy\Exceptions;

use RuntimeException;

/**
 * Thrown when a BelongsToStore-scoped query runs without a bound
 * CurrentStore. Deliberately loud: the alternative failure modes are
 * silently returning zero rows (looks like an empty state, hides the bug)
 * or — far worse — silently returning every tenant's rows. Neither is
 * acceptable, so this fails the request instead.
 */
class StoreContextMissingException extends RuntimeException {}
