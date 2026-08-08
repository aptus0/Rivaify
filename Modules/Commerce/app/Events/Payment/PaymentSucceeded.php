<?php

namespace Modules\Commerce\Events\Payment;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Commerce\Models\Payment\Payment;

class PaymentSucceeded
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Payment $payment) {}
}