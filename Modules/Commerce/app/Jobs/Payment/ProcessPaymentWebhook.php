<?php

namespace Modules\Commerce\Jobs\Payment;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Commerce\Models\Payment\WebhookEvent;
use Modules\Commerce\Services\Payment\WebhookProcessor;

class ProcessPaymentWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $webhookEventId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(WebhookProcessor $processor): void
    {
        $event = WebhookEvent::query()->findOrFail($this->webhookEventId);
        $processor->process($event);
    }
}