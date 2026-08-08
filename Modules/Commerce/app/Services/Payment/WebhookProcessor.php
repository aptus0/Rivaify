<?php

namespace Modules\Commerce\Services\Payment;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Enums\Payment\PaymentStatus;
use Modules\Commerce\Models\Payment\Payment;
use Modules\Commerce\Models\Payment\WebhookEvent;
use Modules\Commerce\Services\Checkout\CheckoutOrchestrator;
use Modules\Store\Models\Store;

class WebhookProcessor
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly PaymentManager $payments,
        private readonly CheckoutOrchestrator $orchestrator,
    ) {}

    public function process(WebhookEvent $event): WebhookEvent
    {
        $event = DB::transaction(function () use ($event) {
            $event = WebhookEvent::query()->lockForUpdate()->findOrFail($event->id);
            if ($event->status === 'processed') {
                return $event;
            }

            $event->increment('attempts');
            $event->update(['status' => 'processing', 'last_error' => null]);

            return $event->refresh();
        });
        if ($event->status === 'processed') {
            return $event;
        }

        try {
            $webhook = $this->payments->gateway($event->provider)->verifyWebhook($event->payload);
            $payment = Payment::withoutGlobalScope(StoreScope::class)
                ->where('provider', $event->provider)
                ->where('provider_payment_id', $webhook->providerPaymentId)
                ->firstOrFail();
            $store = Store::query()->findOrFail($payment->store_id);
            $this->currentStore->set($store);

            $payment = $this->payments->applyWebhook($event->provider, $webhook);
            match ($payment->status) {
                PaymentStatus::Paid => $this->orchestrator->completePaidPayment($payment),
                PaymentStatus::Failed => $this->orchestrator->failPayment($payment),
                default => null,
            };

            $event->update(['status' => 'processed', 'processed_at' => now()]);

            return $event->refresh();
        } catch (\Throwable $exception) {
            $event->update([
                'status' => 'failed',
                'last_error' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            $this->currentStore->clear();
        }
    }
}