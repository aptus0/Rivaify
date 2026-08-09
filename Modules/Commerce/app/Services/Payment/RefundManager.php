<?php

namespace Modules\Commerce\Services\Payment;

use Illuminate\Support\Facades\DB;
use Modules\Commerce\Enums\Payment\PaymentStatus;
use Modules\Commerce\Enums\Payment\RefundStatus;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Payment\Payment;
use Modules\Commerce\Models\Payment\Refund;
use Modules\Commerce\Models\Returns\ReturnRequest;
use Modules\Commerce\Services\Finance\FinanceLedger;
use Modules\Commerce\Services\Order\OrderTimeline;
use Modules\Commerce\ValueObjects\Money;

class RefundManager
{
    public function __construct(
        private readonly FinanceLedger $ledger,
        private readonly OrderTimeline $timeline,
    ) {}

    public function refund(Order $order, Money $amount, string $idempotencyKey, ?ReturnRequest $return = null, ?Payment $payment = null, ?int $userId = null, string $reason = 'merchant_refund'): Refund
    {
        if ($amount->amount <= 0 || $amount->currency !== $order->currency) {
            throw new \InvalidArgumentException('İade tutarı geçerli değil.');
        }

        return DB::transaction(function () use ($order, $amount, $idempotencyKey, $return, $payment, $userId, $reason): Refund {
            $order = Order::query()->with(['payments.refunds'])->lockForUpdate()->findOrFail($order->id);
            $payment ??= $order->payments->first(fn (Payment $candidate): bool => in_array($candidate->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true));
            if ($payment === null) {
                throw new \InvalidArgumentException('İade edilebilir ödeme bulunamadı.');
            }
            if ($payment->order_id !== $order->id) {
                throw new \InvalidArgumentException('Ödeme bu siparişe ait değil.');
            }

            $existing = Refund::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing->load(['order', 'payment', 'returnRequest']);
            }

            $captured = Money::fromDecimal($payment->amount, $payment->currency);
            $alreadyRefunded = $payment->refunds
                ->filter(fn (Refund $refund): bool => $refund->status === RefundStatus::Succeeded)
                ->reduce(fn (Money $total, Refund $refund): Money => $total->add(Money::fromDecimal($refund->amount, $refund->currency)), Money::zero($payment->currency));
            if ($alreadyRefunded->add($amount)->isGreaterThan($captured)) {
                throw new \InvalidArgumentException('İade tutarı ödeme bakiyesini aşamaz.');
            }

            $refund = Refund::query()->create([
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'return_id' => $return?->id,
                'provider' => $payment->provider,
                'idempotency_key' => $idempotencyKey,
                'provider_refund_id' => 'mock_refund_'.str()->ulid(),
                'amount' => $amount->toDecimal(),
                'currency' => $amount->currency,
                'status' => RefundStatus::Succeeded,
                'reason' => $reason,
                'created_by' => $userId,
                'requested_at' => now(),
                'completed_at' => now(),
            ]);
            $newRefunded = $alreadyRefunded->add($amount);
            $payment->update([
                'status' => $newRefunded->amount >= $captured->amount ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
            ]);
            $order->update([
                'payment_status' => $newRefunded->amount >= Money::fromDecimal($order->grand_total, $order->currency)->amount
                    ? \Modules\Commerce\Enums\Order\PaymentStatus::Refunded
                    : \Modules\Commerce\Enums\Order\PaymentStatus::PartiallyRefunded,
            ]);
            if ($return !== null) {
                $return->update([
                    'status' => \Modules\Commerce\Enums\Returns\ReturnStatus::Refunded,
                    'completed_at' => now(),
                ]);
            }
            $this->ledger->recordRefund($refund->load('order'));
            $this->timeline->record($order, 'refund.succeeded', 'İade başarıyla tamamlandı.', metadata: [
                'refund_id' => $refund->ulid,
                'amount' => $refund->amount,
            ]);

            return $refund->load(['order', 'payment', 'returnRequest']);
        });
    }
}
