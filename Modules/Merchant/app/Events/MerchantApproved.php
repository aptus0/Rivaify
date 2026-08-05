<?php

namespace Modules\Merchant\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Models\Store;

class MerchantApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Merchant $merchant,
        public readonly Store $store,
    ) {}
}
