<?php

namespace Modules\Store\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Store\Models\Store;

class StoreCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Store $store) {}
}
