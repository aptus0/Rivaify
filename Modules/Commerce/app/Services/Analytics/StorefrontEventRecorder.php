<?php

namespace Modules\Commerce\Services\Analytics;

use App\Core\Tenancy\CurrentStore;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Enums\Analytics\StorefrontEventType;
use Modules\Commerce\Enums\Order\PaymentStatus as OrderPaymentStatus;
use Modules\Commerce\Enums\Payment\PaymentStatus as GatewayPaymentStatus;
use Modules\Commerce\Models\Analytics\StorefrontEvent;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Payment\Payment;

class StorefrontEventRecorder
{
    public function __construct(private readonly CurrentStore $currentStore) {}

    /**
     * @param  array{utm_source?: string|null, utm_medium?: string|null, utm_campaign?: string|null, referrer_host?: string|null}  $attribution
     */
    public function recordClient(
        StorefrontEventType $type,
        string $sessionId,
        ?Product $product,
        ?CheckoutSession $checkout,
        ?string $pagePath,
        array $attribution,
        string $requestHost,
    ): StorefrontEvent {
        if ($type === StorefrontEventType::Purchase) {
            throw new \InvalidArgumentException('Purchase events are server-only.');
        }

        $utmSource = $this->attributionValue($attribution['utm_source'] ?? null, 100);
        $utmMedium = $this->attributionValue($attribution['utm_medium'] ?? null, 100);
        $utmCampaign = $this->attributionValue($attribution['utm_campaign'] ?? null, 150);
        $referrerHost = $this->host($attribution['referrer_host'] ?? null);
        $currentHost = $this->host($requestHost);

        return StorefrontEvent::query()->create([
            'event_type' => $type,
            'session_hash' => $this->sessionHash($sessionId),
            'product_id' => $product?->id,
            'checkout_id' => $checkout?->id,
            'page_path' => $this->pagePath($type, $pagePath),
            'source' => $this->source($utmSource, $referrerHost, $currentHost),
            'utm_source' => $utmSource,
            'utm_medium' => $utmMedium,
            'utm_campaign' => $utmCampaign,
            'referrer_host' => $referrerHost,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Records a purchase only after the trusted payment/order flow has marked
     * both records paid. Telemetry is isolated in a savepoint and never makes
     * a successfully completed checkout fail.
     */
    public function recordPurchase(Payment $payment): void
    {
        try {
            DB::transaction(function () use ($payment): void {
                $payment = Payment::query()
                    ->with(['order', 'checkout'])
                    ->findOrFail($payment->id);
                $order = $payment->order;
                if (
                    $payment->status !== GatewayPaymentStatus::Paid
                    || $order === null
                    || $order->payment_status !== OrderPaymentStatus::Paid
                    || $order->checkout_id === null
                    || $order->checkout_id !== $payment->checkout_id
                ) {
                    return;
                }

                $checkoutStarted = StorefrontEvent::query()
                    ->where('event_type', StorefrontEventType::CheckoutStarted->value)
                    ->where('checkout_id', $order->checkout_id)
                    ->latest('occurred_at')
                    ->first();

                StorefrontEvent::query()->firstOrCreate([
                    'event_type' => StorefrontEventType::Purchase,
                    'order_id' => $order->id,
                ], [
                    'session_hash' => $checkoutStarted?->session_hash
                        ?? $this->sessionHash('server-purchase-'.$order->ulid),
                    'checkout_id' => $order->checkout_id,
                    'page_path' => '/checkouts/:token/confirmation',
                    'source' => $checkoutStarted?->source ?? 'direct',
                    'utm_source' => $checkoutStarted?->utm_source,
                    'utm_medium' => $checkoutStarted?->utm_medium,
                    'utm_campaign' => $checkoutStarted?->utm_campaign,
                    'referrer_host' => $checkoutStarted?->referrer_host,
                    'occurred_at' => $payment->paid_at ?? $order->placed_at ?? now(),
                ]);
            });
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function sessionHash(string $sessionId): string
    {
        $key = (string) config('app.key').'|store:'.$this->currentStore->id();

        return hash_hmac('sha256', $sessionId, $key);
    }

    private function attributionValue(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_strtolower(trim($value));
        if ($value === '' || mb_strlen($value) > $maxLength) {
            return null;
        }
        if (str_contains($value, '@') || preg_match('/\d{7,}/', $value) === 1) {
            return null;
        }

        $value = preg_replace('/[^\pL\pN._~+\/-]+/u', '-', $value);
        $value = trim((string) $value, '-');

        return $value === '' ? null : $value;
    }

    private function host(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $host = mb_strtolower(trim($value, " \t\n\r\0\x0B."));
        if ($host === '' || mb_strlen($host) > 253 || filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }
        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            return null;
        }

        return str_starts_with($host, 'www.') ? mb_substr($host, 4) : $host;
    }

    private function source(?string $utmSource, ?string $referrerHost, ?string $currentHost): string
    {
        if ($utmSource !== null) {
            return $utmSource;
        }
        if ($referrerHost === null || $referrerHost === $currentHost) {
            return 'direct';
        }

        return match (true) {
            str_contains($referrerHost, 'google.') => 'google',
            str_contains($referrerHost, 'bing.') => 'bing',
            str_contains($referrerHost, 'instagram.') => 'instagram',
            str_contains($referrerHost, 'facebook.'), $referrerHost === 'fb.com' => 'facebook',
            str_contains($referrerHost, 'tiktok.') => 'tiktok',
            $referrerHost === 't.co', str_contains($referrerHost, 'twitter.'), str_contains($referrerHost, 'x.com') => 'x',
            str_contains($referrerHost, 'youtube.') => 'youtube',
            default => $referrerHost,
        };
    }

    private function pagePath(StorefrontEventType $type, ?string $path): ?string
    {
        if ($type === StorefrontEventType::ProductView) {
            return '/products/:product';
        }
        if ($type === StorefrontEventType::CheckoutStarted) {
            return '/checkouts/:token';
        }
        if (! is_string($path) || ! str_starts_with($path, '/')) {
            return null;
        }

        $path = parse_url($path, PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }
        $path = preg_replace('#/+#', '/', $path) ?: '/';

        return match (true) {
            $path === '/', $path === '/cart' => $path,
            preg_match('#^/products/[^/]+$#', $path) === 1 => '/products/:product',
            preg_match('#^/checkouts/[^/]+/confirmation$#', $path) === 1 => '/checkouts/:token/confirmation',
            preg_match('#^/checkouts/[^/]+$#', $path) === 1 => '/checkouts/:token',
            default => '/other',
        };
    }
}
