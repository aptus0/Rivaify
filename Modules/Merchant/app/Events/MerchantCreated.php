<?php

namespace Modules\Merchant\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Merchant\Models\Merchant;

class MerchantCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Merchant $merchant) {}
}
