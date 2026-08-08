<?php

namespace Modules\Commerce\Exceptions\Payment;

use RuntimeException;

class IdempotencyKeyConflictException extends RuntimeException {}