<?php

namespace App\Core\Internal\Http\Controllers;

use App\Core\Internal\Models\OperationCase;
use App\Core\Tenancy\Scopes\StoreScope;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Payment\Payment;
use Modules\Commerce\Models\Payment\Refund;
use Modules\Commerce\Models\Returns\ReturnRequest;
use Modules\Commerce\Models\Shipping\Shipment;
use Modules\Store\Models\Store;
use Modules\Verification\Models\VerificationRequest;

class InternalDashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $today = CarbonImmutable::today();

        $metrics = [
            'new_verifications' => VerificationRequest::withoutGlobalScope(StoreScope::class)->where('status', 'pending')->count(),
            'verification_in_review' => VerificationRequest::withoutGlobalScope(StoreScope::class)->where('status', 'in_review')->count(),
            'active_stores' => Store::query()->where('status', 'active')->count(),
            'orders_today' => Order::withoutGlobalScope(StoreScope::class)->where('created_at', '>=', $today)->count(),
            'payment_issues' => Payment::withoutGlobalScope(StoreScope::class)->where('status', 'failed')->where('created_at', '>=', $today->subDays(7))->count(),
            'refund_failures' => Refund::withoutGlobalScope(StoreScope::class)->where('status', 'failed')->count(),
            'shipping_failures' => Shipment::withoutGlobalScope(StoreScope::class)->whereIn('status', ['failed', 'cancelled'])->count(),
            'support_tickets' => OperationCase::query()->where('type', 'SUPPORT')->whereNotIn('status', ['RESOLVED', 'CLOSED'])->count(),
            'critical_alerts' => OperationCase::query()->where('priority', 'CRITICAL')->whereNotIn('status', ['RESOLVED', 'CLOSED'])->count(),
        ];

        return response()->json(['data' => [
            'date' => now()->locale('tr')->translatedFormat('j F Y'),
            'metrics' => $metrics,
            'action_center' => $this->actions($metrics),
            'system' => [
                'laravel' => 'healthy',
                'database' => $this->databaseStatus(),
                'queue_waiting' => DB::table('jobs')->count(),
                'failed_jobs' => DB::table('failed_jobs')->count(),
            ],
        ]]);
    }

    /**
     * @param array<string, int> $metrics
     * @return array<int, array<string, string>>
     */
    private function actions(array $metrics): array
    {
        $actions = [];

        if ($metrics['refund_failures'] > 0) {
            $actions[] = [
                'severity' => 'HIGH',
                'title' => "{$metrics['refund_failures']} refund işlemi başarısız oldu",
                'detail' => 'Provider yanıtı ve retry uygunluğu incelenmeli.',
                'href' => '/cases?type=REFUND_FAILURE',
            ];
        }

        if ($metrics['payment_issues'] > 0) {
            $actions[] = [
                'severity' => 'HIGH',
                'title' => "{$metrics['payment_issues']} ödeme işlemi dikkat istiyor",
                'detail' => 'Son 7 gündeki başarısız ödeme kayıtları.',
                'href' => '/payments?status=failed',
            ];
        }

        if ($metrics['new_verifications'] > 0) {
            $actions[] = [
                'severity' => $metrics['new_verifications'] >= 10 ? 'MEDIUM' : 'LOW',
                'title' => "{$metrics['new_verifications']} doğrulama başvurusu bekliyor",
                'detail' => 'Merchant başvuruları gönderim sırasına göre incelenmeli.',
                'href' => '/verifications',
            ];
        }

        if ($metrics['shipping_failures'] > 0) {
            $actions[] = [
                'severity' => 'MEDIUM',
                'title' => "{$metrics['shipping_failures']} kargo kaydı hata durumunda",
                'detail' => 'Provider veya adres kaynaklı sorun olabilir.',
                'href' => '/shipments?status=failed',
            ];
        }

        if ($metrics['critical_alerts'] > 0) {
            $actions[] = [
                'severity' => 'CRITICAL',
                'title' => "{$metrics['critical_alerts']} kritik operasyon case'i açık",
                'detail' => 'Kritik case queue öncelikli ele alınmalı.',
                'href' => '/cases?priority=CRITICAL',
            ];
        }

        return $actions;
    }

    private function databaseStatus(): string
    {
        try {
            DB::select('select 1');

            return 'healthy';
        } catch (\Throwable) {
            return 'degraded';
        }
    }
}
