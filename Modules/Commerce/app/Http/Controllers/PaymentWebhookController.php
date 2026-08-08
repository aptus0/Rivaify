<?php

namespace Modules\Commerce\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Exceptions\Payment\PaymentGatewayNotConfiguredException;
use Modules\Commerce\Jobs\Payment\ProcessPaymentWebhook;
use Modules\Commerce\Services\Payment\WebhookInbox;

class PaymentWebhookController extends Controller
{
    public function receive(Request $request, string $provider, WebhookInbox $inbox): JsonResponse
    {
        try {
            $event = $inbox->receive($provider, $request->all(), $this->headers($request));
        } catch (PaymentGatewayNotConfiguredException $exception) {
            return response()->json(['message' => 'payment_provider_not_found'], 404);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => 'invalid_payment_webhook'], 400);
        }

        if ($event->wasRecentlyCreated) {
            ProcessPaymentWebhook::dispatch($event->id);
        }

        return response()->json(['received' => true], 200);
    }

    /**
     * @return array<string, string>
     */
    private function headers(Request $request): array
    {
        return collect($request->headers->all())
            ->map(fn (array $values): string => (string) ($values[0] ?? ''))
            ->all();
    }
}