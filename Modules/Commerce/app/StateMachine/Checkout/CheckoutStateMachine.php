<?php

namespace Modules\Commerce\StateMachine\Checkout;

use Modules\Commerce\Enums\Checkout\CheckoutState;
use Modules\Commerce\Exceptions\Checkout\InvalidCheckoutTransitionException;
use Modules\Commerce\Models\Checkout\CheckoutSession;

class CheckoutStateMachine
{
    public function transition(CheckoutSession $checkout, CheckoutState $nextState): CheckoutSession
    {
        if ($checkout->status === $nextState) {
            return $checkout;
        }

        if (! $this->canTransition($checkout->status, $nextState)) {
            throw new InvalidCheckoutTransitionException(
                "Cannot transition checkout from [{$checkout->status->value}] to [{$nextState->value}]."
            );
        }

        $attributes = ['status' => $nextState];
        if ($nextState === CheckoutState::Completed) {
            $attributes['completed_at'] = now();
        }

        $checkout->update($attributes);

        return $checkout->refresh();
    }

    public function canTransition(CheckoutState $from, CheckoutState $to): bool
    {
        return in_array($to, match ($from) {
            CheckoutState::Initiated => [CheckoutState::CustomerInformation, CheckoutState::Expired],
            CheckoutState::CustomerInformation => [CheckoutState::Address, CheckoutState::Expired],
            CheckoutState::Address => [CheckoutState::Shipping, CheckoutState::Expired],
            CheckoutState::Shipping => [CheckoutState::Payment, CheckoutState::Expired],
            CheckoutState::Payment => [CheckoutState::Processing, CheckoutState::Failed, CheckoutState::Expired],
            CheckoutState::Processing => [CheckoutState::Completed, CheckoutState::Failed],
            CheckoutState::Failed => [CheckoutState::Payment, CheckoutState::Expired],
            CheckoutState::Completed, CheckoutState::Expired => [],
        }, true);
    }
}