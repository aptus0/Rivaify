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
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Payment\Payment;
use Modules\Commerce\Models\Payment\PaymentTransaction;
use Modules\Commerce\Providers\Payment\ManualPaymentGateway;
use Modules\Commerce\Services\Order\OrderTimeline;
use Modules\Commerce\ValueObjects\Money;

class PaymentManager
{
    /** @var array<string, PaymentGateway> */
    private array $gateways;

    public function __construct(
        private readonly CurrentStore $currentStore,
        ManualPaymentGateway $manualGateway,
        private readonly OrderTimeline $timeline,
    ) {
        $this->gateways = [$manualGateway->name() => $manualGateway];
    }

    public function createPayment(CheckoutSession $checkout, string $provider, ?string $paymentMethodType = null): Payment
    {
        $gateway = $this->gateway($provider);
        $payment = $this->preparePayment($checkout, $provider, $paymentMethodType);

        try {
            $result = $gateway->createPayment(new GatewayPaymentRequest(
                reference: $payment->ulid,
                amount: Money::fromDecimal($payment->amount, $payment->currency),
                paymentMethodType: $payment->payment_method_type,
                metadata: ['checkout_token' => $checkout->token],
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
        if ($payment->amount !== $webhook->amount->toDecimal() || $payment->currency !== $webhook->amount->currency) {
            return $this->recordFailure($payment, 'amount_mismatch', 'Webhook amount does not match payment amount.', true);
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
        $payment = $this->prepareRefund($payment, $amount);
        $gateway = $this->gateway($payment->provider);

        try {
            $result = $gateway->refund($payment->provider_payment_id, new GatewayPaymentRequest(
                reference: "refund:{$payment->ulid}",
                amount: $amount,
                paymentMethodType: $payment->payment_method_type,
            ));
        } catch (\Throwable $exception) {
            return $this->recordRefundFailure($payment, $amount, 'gateway_exception', $exception->getMessage());
        }

        return $this->recordRefundResult($payment, $amount, $result);
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
                ->whereNull('provider_payment_id')
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
            $payment->transactions()->firstOrCreate([
                'type' => PaymentTransactionType::Sale,
                'provider_transaction_id' => $result->providerTransactionId,
            ], [
                'status' => $isSuccessful ? PaymentTransactionStatus::Succeeded : PaymentTransactionStatus::Failed,
                'amount' => $payment->amount,
                'metadata' => $result->metadata,
            ]);
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

    private function prepareRefund(Payment $payment, Money $amount): Payment
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

            $remaining = Money::fromDecimal($payment->amount, $payment->currency)
                ->subtract($this->successfulRefundTotal($payment));
            if ($amount->isGreaterThan($remaining)) {
                throw new \InvalidArgumentException('Refund amount exceeds the refundable payment balance.');
            }

            return $payment;
        });
    }

    private function recordRefundResult(Payment $payment, Money $amount, GatewayPaymentResult $result): Payment
    {
        $isSuccessful = in_array($result->status, [PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded], true);

        return DB::transaction(function () use ($payment, $amount, $result, $isSuccessful) {
            $payment = Payment::query()->with('order')->lockForUpdate()->findOrFail($payment->id);
            $transaction = $payment->transactions()->firstOrCreate([
                'type' => PaymentTransactionType::Refund,
                'provider_transaction_id' => $result->providerTransactionId,
            ], [
                'status' => $isSuccessful ? PaymentTransactionStatus::Succeeded : PaymentTransactionStatus::Failed,
                'amount' => $amount->toDecimal(),
                'metadata' => $result->metadata,
            ]);
            if (! $isSuccessful || ! $transaction->wasRecentlyCreated) {
                return $payment;
            }

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
                    'amount' => $amount->toDecimal(),
                ]);
            }
            $payment = $payment->refresh();
            PaymentRefunded::dispatch($payment);

            return $payment;
        });
    }

    private function recordRefundFailure(Payment $payment, Money $amount, string $code, string $message): Payment
    {
        return DB::transaction(function () use ($payment, $amount, $code, $message) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $payment->transactions()->create([
                'type' => PaymentTransactionType::Refund,
                'status' => PaymentTransactionStatus::Failed,
                'amount' => $amount->toDecimal(),
                'provider_transaction_id' => 'refund_failed_'.str()->ulid(),
                'metadata' => ['failure_code' => $code, 'failure_message' => $message],
            ]);

            return $payment;
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