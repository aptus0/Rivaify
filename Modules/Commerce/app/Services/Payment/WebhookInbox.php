<?php

namespace Modules\Commerce\Services\Payment;

use Illuminate\Support\Facades\DB;
use Modules\Commerce\Models\Payment\WebhookEvent;

class WebhookInbox
{
    public function __construct(private readonly PaymentManager $payments) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function receive(string $provider, array $payload, array $headers = []): WebhookEvent
    {
        $provider = mb_strtolower(trim($provider));
        $verified = $this->payments->gateway($provider)->verifyWebhook($payload, $headers);

        return DB::transaction(function () use ($provider, $payload, $verified) {
            return WebhookEvent::query()->firstOrCreate([
                'provider' => $provider,
                'external_event_id' => $verified->externalEventId,
            ], [
                'type' => $verified->type,
                'payload' => $payload,
                'status' => 'received',
                'received_at' => now(),
            ]);
        });
    }
}