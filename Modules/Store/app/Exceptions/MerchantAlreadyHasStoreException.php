<?php

namespace Modules\Store\Exceptions;

use RuntimeException;

/**
 * Sprint 01 assumes one store per merchant (brief: "daha ürün bile
 * eklemiyoruz... önce sağlam tenant sistemi"). Multi-store-per-merchant is
 * an explicit future extension, not something to half-support now.
 */
class MerchantAlreadyHasStoreException extends RuntimeException {}
