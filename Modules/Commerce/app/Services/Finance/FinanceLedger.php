<?php

namespace Modules\Commerce\Services\Finance;

use Illuminate\Support\Facades\DB;
use Modules\Commerce\Enums\Finance\FinancialTransactionType;
use Modules\Commerce\Models\Finance\FinancialTransaction;
use Modules\Commerce\Models\Finance\MerchantSettlement;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Payment\Refund;

class FinanceLedger
{
    public function recordSale(Order $order): FinancialTransaction
    {
        return $this->record(
            order: $order,
            type: FinancialTransactionType::Sale,
            gross: (string) $order->grand_total,
            net: (string) $order->grand_total,
            currency: $order->currency,
            reference: $order,
            occurredAt: $order->placed_at ?? now(),
        );
    }

    public function recordRefund(Refund $refund): FinancialTransaction
    {
        $type = $refund->amount === $refund->order->grand_total
            ? FinancialTransactionType::Refund
            : FinancialTransactionType::PartialRefund;

        return $this->record(
            order: $refund->order,
            type: $type,
            gross: '-'.$refund->amount,
            net: '-'.$refund->amount,
            currency: $refund->currency,
            reference: $refund,
            occurredAt: $refund->completed_at ?? now(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function merchantSummary(string $currency = 'TRY'): array
    {
        $transactions = FinancialTransaction::query()->where('currency', $currency)->get();
        $gross = $transactions->where('type', FinancialTransactionType::Sale)->sum(fn ($row): float => (float) $row->gross_amount);
        $refunds = abs($transactions
            ->filter(fn ($row): bool => in_array($row->type, [FinancialTransactionType::Refund, FinancialTransactionType::PartialRefund], true))
            ->sum(fn ($row): float => (float) $row->gross_amount));
        $platformFees = abs($transactions->sum(fn ($row): float => (float) $row->platform_fee));
        $providerFees = abs($transactions->sum(fn ($row): float => (float) $row->provider_fee));
        $net = $transactions->sum(fn ($row): float => (float) $row->net_amount);
        $settlements = MerchantSettlement::query()->where('currency', $currency)->latest('period_end')->limit(10)->get();

        return [
            'currency' => $currency,
            'gross_sales' => number_format($gross, 2, '.', ''),
            'refunds' => number_format($refunds, 2, '.', ''),
            'platform_fees' => number_format($platformFees, 2, '.', ''),
            'provider_fees' => number_format($providerFees, 2, '.', ''),
            'net_sales' => number_format($net, 2, '.', ''),
            'payouts' => [
                'pending' => number_format((float) $settlements->where('status', 'pending')->sum('net'), 2, '.', ''),
                'processing' => number_format((float) $settlements->where('status', 'processing')->sum('net'), 2, '.', ''),
                'paid' => number_format((float) $settlements->where('status', 'paid')->sum('net'), 2, '.', ''),
            ],
            'settlements' => $settlements->map(fn (MerchantSettlement $settlement): array => [
                'id' => $settlement->ulid,
                'provider' => $settlement->provider,
                'gross' => $settlement->gross,
                'fees' => $settlement->fees,
                'refunds' => $settlement->refunds,
                'net' => $settlement->net,
                'expected_net' => $settlement->expected_net,
                'difference' => $settlement->difference,
                'status' => $settlement->status,
                'period_start' => $settlement->period_start?->toDateString(),
                'period_end' => $settlement->period_end?->toDateString(),
                'expected_at' => $settlement->expected_at?->toIso8601String(),
                'paid_at' => $settlement->paid_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    public function reconcileSettlement(MerchantSettlement $settlement): MerchantSettlement
    {
        $expected = FinancialTransaction::query()
            ->where('currency', $settlement->currency)
            ->whereBetween('occurred_at', [$settlement->period_start->startOfDay(), $settlement->period_end->endOfDay()])
            ->sum('net_amount');
        $difference = (float) $settlement->net - (float) $expected;
        $settlement->update([
            'expected_net' => number_format((float) $expected, 2, '.', ''),
            'difference' => number_format($difference, 2, '.', ''),
            'status' => abs($difference) > 0.005 ? 'mismatch' : $settlement->status,
        ]);

        return $settlement->refresh();
    }

    private function record(
        Order $order,
        FinancialTransactionType $type,
        string $gross,
        string $net,
        string $currency,
        object $reference,
        mixed $occurredAt,
    ): FinancialTransaction {
        return DB::transaction(fn (): FinancialTransaction => FinancialTransaction::query()->firstOrCreate([
            'type' => $type,
            'reference_type' => $reference::class,
            'reference_id' => $reference->id,
        ], [
            'order_id' => $order->id,
            'gross_amount' => $gross,
            'platform_fee' => '0.00',
            'provider_fee' => '0.00',
            'net_amount' => $net,
            'currency' => $currency,
            'status' => 'posted',
            'occurred_at' => $occurredAt,
        ]));
    }
}
