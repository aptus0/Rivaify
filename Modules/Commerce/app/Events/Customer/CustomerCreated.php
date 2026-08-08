<?php

namespace Modules\Commerce\Events\Customer;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Commerce\Models\Customer\Customer;

class CustomerCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Customer $customer) {}
}