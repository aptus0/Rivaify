<?php

namespace Modules\Commerce\Services\Payment;

use App\Core\Tenancy\CurrentStore;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Contracts\Payment\PaymentGateway;
use Modules\Commerce\DTOs\Payment\GatewayPaymentRequest;
use Modules\Commerce\DTOs\Payment\GatewayPaymentResult;
use Modules\Commerce\DTOs\Payment\VerifiedPaymentWebhook;
use Modules\Commerce\Enums\Payment\PaymentStatus;
use Modules\Commerce\Enums\Payment\PaymentTransactionStatus;
use Modules\Commerce\Enums\Payment\PaymentTransactionType;
use Modules\Commerce\Events\Payment\PaymentCreated;
use Modules\Commerce\Events\Payment\PaymentFailed;
use Modules\Commerce\Events\Payment\PaymentRefunded;
use Modules\Commerce\Events\Payment\PaymentSucceeded;
use Modules\Commerce\Exceptions\Payment\PaymentGatewayNotConfiguredException;
use Modules\Commerce\Exceptions\Payment\PaymentGatewayRejectedException;
use Modules\Commerce\Exceptions\Payment\PaymentRefundFailedException;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Payment\Payment;
use Modules\Commerce\Models\Payment\PaymentTransaction;
use Modules\Commerce\Providers\Payment\ManualPaymentGateway;
use Modules\Commerce\Providers\Payment\PaytrPaymentGateway;
use Modules\Commerce\Services\Order\OrderTimeline;
use Modules\Commerce\ValueObjects\Money;

class PaymentManager
{
    /** @var array<string, PaymentGateway> */
    private array $gateways;

    public function __construct(
        private readonly CurrentStore $currentStore,
        ManualPaymentGateway $manualGateway,
        PaytrPaymentGateway $paytrGateway,
        private readonly OrderTimeline $timeline,
    ) {
        $this->gateways = [$manualGateway->name() => $manualGateway, $paytrGateway->name() => $paytrGateway];
    }

    public function createPayment(CheckoutSession $checkout, string $provider, ?string $paymentMethodType = null): Payment
    {
        $gateway = $this->gateway($provider);
        $payment = $this->preparePayment($checkout, $provider, $paymentMethodType);
        if ($payment->status === PaymentStatus::Pending && $payment->provider_payment_id !== null && isset($payment->metadata['iframe_url'])) {
            return $payment;
        }

        $checkout->loadMissing(['customer', 'shippingAddress', 'cart.items.product']);
        $address = $checkout->shippingAddress;
        $store = $this->currentStore->store();
        $checkoutUrl = 'https://'.$store->slug.'.rivaify.com/checkouts/'.$checkout->token;
        try {
            $result = $gateway->createPayment(new GatewayPaymentRequest(
                reference: $payment->ulid,
                amount: Money::fromDecimal($payment->amount, $payment->currency),
                paymentMethodType: $payment->payment_method_type,
                metadata: [
                    'checkout_token' => $checkout->token,
                    'user_ip' => request()->ip(),
                    'email' => $checkout->email,
                    'user_name' => mb_substr(trim(($checkout->customer?->first_name ?? '').' '.($checkout->customer?->last_name ?? '')), 0, 60),
                    'user_phone' => mb_substr(trim((string) ($checkout->phone ?? $address?->phone)), 0, 20),
                    'user_address' => mb_substr($address ? implode(' ', array_filter([$address->address_line_1, $address->address_line_2, $address->district, $address->province])) : '', 0, 400),
                    'ok_url' => $checkoutUrl.'?payment=success',
                    'fail_url' => $checkoutUrl.'?payment=failed',
                    'basket' => $checkout->cart->items->map(fn ($item): array => [
                        $item->product?->title ?? 'Ürün',
                        $item->unit_price,
                        $item->quantity,
                    ])->values()->all(),
                ],
            ));
        } catch (\Throwable $exception) {
            return $this->recordFailure($payment, 'gateway_exception', $exception->getMessage());
        }

        return $this->recordGatewayResult($payment, $result);
    }

    public function applyWebhook(string $provider, VerifiedPaymentWebhook $webhook): Payment
    {
        $payment = Payment::query()
            ->where('provider', $provider)
            ->where('provider_payment_id', $webhook->providerPaymentId)
            ->firstOrFail();

        if (in_array($webhook->status, [PaymentStatus::Paid, PaymentStatus::Authorized], true)) {
            $expectedAmount = Money::fromDecimal($payment->amount, $payment->currency);
            $currencyMismatch = $expectedAmount->currency !== $webhook->amount->currency;
            $amountMismatch = $provider === 'paytr'
                ? $webhook->amount->amount < $expectedAmount->amount
                : $webhook->amount->amount !== $expectedAmount->amount;
            $reportedPaymentAmount = $webhook->metadata['payment_amount'] ?? null;
            $reportedAmountMismatch = $provider === 'paytr' && (
                (! is_string($reportedPaymentAmount) && ! is_int($reportedPaymentAmount))
                || preg_match('/^\d+$/', (string) $reportedPaymentAmount) !== 1
                || (int) $reportedPaymentAmount !== $expectedAmount->amount
            );

            if ($currencyMismatch || $amountMismatch || $reportedAmountMismatch) {
                return $this->recordFailure(
                    $payment,
                    'amount_mismatch',
                    'Webhook payment details do not match the expected payment.',
                    $provider !== 'paytr',
                );
            }
        }

        return $this->recordGatewayResult($payment, new GatewayPaymentResult(
            providerPaymentId: $webhook->providerPaymentId,
            providerTransactionId: $webhook->externalEventId,
            status: $webhook->status,
            metadata: $webhook->metadata,
        ));
    }

    public function refund(Payment $payment, Money $amount): Payment
    {
        $attempt = $this->prepareRefundAttempt($payment, $amount);
        $payment = $payment->fresh();
        $gateway = $this->gateway($payment->provider);

        try {
            $result = $gateway->refund((string) $payment->provider_payment_id, new GatewayPaymentRequest(
                reference: "refund:{$attempt->ulid}",
                amount: $amount,
                paymentMethodType: $payment->payment_method_type,
                metadata: ['reference_no' => $attempt->provider_transaction_id],
            ));
        } catch (PaymentGatewayRejectedException $exception) {
            $this->recordRefundFailure(
                $attempt,
                'gateway_rejected',
                'Payment provider rejected the refund request.',
            );

            throw new PaymentRefundFailedException(
                'Payment refund could not be completed.',
                previous: $exception,
            );
        } catch (\Throwable $exception) {
            $this->recordRefundUnknown(
                $attempt,
                'gateway_result_unknown',
                'Payment provider refund result requires reconciliation.',
            );

            throw new PaymentRefundFailedException(
                'Payment refund could not be completed.',
                previous: $exception,
            );
        }

        if (! in_array($result->status, [PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded], true)) {
            $this->recordRefundFailure(
                $attempt,
                'gateway_rejected',
                'Payment provider rejected the refund request.',
            );

            throw new PaymentRefundFailedException('Payment refund could not be completed.');
        }

        return $this->recordRefundResult($payment, $attempt, $result);
    }

    public function gateway(string $provider): PaymentGateway
    {
        $gateway = $this->gateways[mb_strtolower($provider)] ?? null;
        if ($gateway === null) {
            throw new PaymentGatewayNotConfiguredException("Payment provider [{$provider}] is not configured.");
        }

        return $gateway;
    }

    private function preparePayment(CheckoutSession $checkout, string $provider, ?string $paymentMethodType): Payment
    {
        if ($checkout->store_id !== $this->currentStore->id()) {
            throw new \InvalidArgumentException('Checkout does not belong to the current store.');
        }

        return DB::transaction(function () use ($checkout, $provider, $paymentMethodType) {
            $checkout = CheckoutSession::query()->lockForUpdate()->findOrFail($checkout->id);
            $provider = mb_strtolower(trim($provider));
            $pendingPayment = Payment::query()
                ->where('checkout_id', $checkout->id)
                ->where('provider', $provider)
                ->where('status', PaymentStatus::Pending->value)
                ->lockForUpdate()
                ->first();
            if ($pendingPayment !== null) {
                return $pendingPayment;
            }

            $payment = Payment::query()->create([
                'checkout_id' => $checkout->id,
                'provider' => $provider,
                'amount' => $checkout->grand_total,
                'currency' => $checkout->currency,
                'payment_method_type' => $paymentMethodType,
            ]);
            PaymentCreated::dispatch($payment);

            return $payment;
        });
    }

    private function recordGatewayResult(Payment $payment, GatewayPaymentResult $result, bool $allowStatusOverride = false): Payment
    {
        return DB::transaction(function () use ($payment, $result, $allowStatusOverride) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if (! $allowStatusOverride && in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::Authorized], true)) {
                return $payment;
            }

            $isSuccessful = in_array($result->status, [PaymentStatus::Paid, PaymentStatus::Authorized], true);
            $payment->update([
                'provider_payment_id' => $result->providerPaymentId,
                'status' => $result->status,
                'authorized_at' => $result->status === PaymentStatus::Authorized ? now() : $payment->authorized_at,
                'paid_at' => $result->status === PaymentStatus::Paid ? now() : $payment->paid_at,
                'failed_at' => $result->status === PaymentStatus::Failed ? now() : $payment->failed_at,
                'failure_code' => $result->failureCode,
                'failure_message' => $result->failureMessage,
                'metadata' => $result->metadata,
            ]);
            if ($result->status !== PaymentStatus::Pending) {
                $payment->transactions()->firstOrCreate([
                    'type' => PaymentTransactionType::Sale,
                    'provider_transaction_id' => $result->providerTransactionId,
                ], [
                    'status' => $isSuccessful ? PaymentTransactionStatus::Succeeded : PaymentTransactionStatus::Failed,
                    'amount' => $payment->amount,
                    'metadata' => $result->metadata,
                ]);
            }
            $payment = $payment->refresh();

            match ($payment->status) {
                PaymentStatus::Paid => PaymentSucceeded::dispatch($payment),
                PaymentStatus::Failed => PaymentFailed::dispatch($payment),
                default => null,
            };

            return $payment;
        });
    }

    private function recordFailure(Payment $payment, string $code, string $message, bool $allowStatusOverride = false): Payment
    {
        return $this->recordGatewayResult($payment, new GatewayPaymentResult(
            providerPaymentId: $payment->provider_payment_id ?? 'failed_'.$payment->ulid,
            providerTransactionId: 'failed_'.$payment->ulid,
            status: PaymentStatus::Failed,
            failureCode: $code,
            failureMessage: $message,
        ), $allowStatusOverride);
    }

    private function prepareRefundAttempt(Payment $payment, Money $amount): PaymentTransaction
    {
        if ($payment->store_id !== $this->currentStore->id()) {
            throw new \InvalidArgumentException('Payment does not belong to the current store.');
        }
        if ($amount->amount <= 0 || $amount->currency !== $payment->currency) {
            throw new \InvalidArgumentException('Refund amount must be positive and use the payment currency.');
        }

        return DB::transaction(function () use ($payment, $amount) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if (! in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true)) {
                throw new \InvalidArgumentException('Only paid payments can be refunded.');
            }
            if ($payment->provider_payment_id === null) {
                throw new \InvalidArgumentException('Payment provider reference is required for refunds.');
            }
            $pendingRefundExists = $payment->transactions()
                ->where('type', PaymentTransactionType::Refund->value)
                ->where('status', PaymentTransactionStatus::Pending->value)
                ->exists();
            if ($pendingRefundExists) {
                throw new \InvalidArgumentException('A refund is already in progress for this payment.');
            }

            $remaining = Money::fromDecimal($payment->amount, $payment->currency)
                ->subtract($this->successfulRefundTotal($payment));
            if ($amount->isGreaterThan($remaining)) {
                throw new \InvalidArgumentException('Refund amount exceeds the refundable payment balance.');
            }

            $attemptUlid = (string) str()->ulid();
            $attempt = $payment->transactions()->make([
                'type' => PaymentTransactionType::Refund,
                'status' => PaymentTransactionStatus::Pending,
                'amount' => $amount->toDecimal(),
                'provider_transaction_id' => 'R'.$attemptUlid,
                'metadata' => ['attempt_ulid' => $attemptUlid],
            ]);
            $attempt->ulid = $attemptUlid;
            $attempt->save();

            return $attempt;
        });
    }

    private function recordRefundResult(
        Payment $payment,
        PaymentTransaction $attempt,
        GatewayPaymentResult $result,
    ): Payment {
        return DB::transaction(function () use ($payment, $attempt, $result) {
            $payment = Payment::query()->with('order')->lockForUpdate()->findOrFail($payment->id);
            $transaction = PaymentTransaction::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($transaction->status !== PaymentTransactionStatus::Pending) {
                return $payment;
            }
            $transaction->update([
                'status' => PaymentTransactionStatus::Succeeded,
                'metadata' => array_merge(
                    $transaction->metadata ?? [],
                    $result->metadata,
                    ['provider_result_id' => $result->providerTransactionId],
                ),
            ]);

            $refundedTotal = $this->successfulRefundTotal($payment);
            $paymentTotal = Money::fromDecimal($payment->amount, $payment->currency);
            $status = $refundedTotal->isGreaterThan($paymentTotal) || $refundedTotal->amount === $paymentTotal->amount
                ? PaymentStatus::Refunded
                : PaymentStatus::PartiallyRefunded;
            $payment->update(['status' => $status]);
            if ($payment->order !== null) {
                $orderStatus = $status === PaymentStatus::Refunded
                    ? \Modules\Commerce\Enums\Order\PaymentStatus::Refunded
                    : \Modules\Commerce\Enums\Order\PaymentStatus::PartiallyRefunded;
                $payment->order->update(['payment_status' => $orderStatus]);
                $this->timeline->record($payment->order, 'refund_issued', 'A payment refund was issued.', metadata: [
                    'payment_id' => $payment->ulid,
                    'amount' => $transaction->amount,
                ]);
            }
            $payment = $payment->refresh();
            PaymentRefunded::dispatch($payment);

            return $payment;
        });
    }

    private function recordRefundFailure(
        PaymentTransaction $attempt,
        string $code,
        string $message,
    ): void {
        DB::transaction(function () use ($attempt, $code, $message) {
            $transaction = PaymentTransaction::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($transaction->status !== PaymentTransactionStatus::Pending) {
                return;
            }
            $transaction->update([
                'status' => PaymentTransactionStatus::Failed,
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'failure_code' => $code,
                    'failure_message' => $message,
                ]),
            ]);
        });
    }

    private function recordRefundUnknown(
        PaymentTransaction $attempt,
        string $code,
        string $message,
    ): void {
        DB::transaction(function () use ($attempt, $code, $message) {
            $transaction = PaymentTransaction::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($transaction->status !== PaymentTransactionStatus::Pending) {
                return;
            }
            $transaction->update([
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'failure_code' => $code,
                    'failure_message' => $message,
                    'reconciliation_required' => true,
                ]),
            ]);
        });
    }

    private function successfulRefundTotal(Payment $payment): Money
    {
        return $payment->transactions()
            ->where('type', PaymentTransactionType::Refund->value)
            ->where('status', PaymentTransactionStatus::Succeeded->value)
            ->get()
            ->reduce(
                fn (Money $total, PaymentTransaction $transaction): Money => $total->add(
                    Money::fromDecimal($transaction->amount, $payment->currency),
                ),
                Money::zero($payment->currency),
            );
    }
}
