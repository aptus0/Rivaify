<?php

namespace Modules\Commerce\Services\Analytics;

use Carbon\CarbonInterface;
use Modules\Commerce\Enums\Analytics\StorefrontEventType;
use Modules\Commerce\Models\Analytics\StorefrontEvent;

class TrafficAnalytics
{
    /** @return array<string, mixed> */
    public function summarize(CarbonInterface $from, CarbonInterface $to): array
    {
        $events = StorefrontEvent::query()->whereBetween('occurred_at', [$from, $to]);
        $totalEvents = (clone $events)->count();

        $counts = (clone $events)
            ->selectRaw('event_type, COUNT(DISTINCT session_hash) AS sessions')
            ->groupBy('event_type')
            ->get()
            ->mapWithKeys(fn (StorefrontEvent $row): array => [
                $row->event_type->value => (int) $row->getAttribute('sessions'),
            ]);

        $sourceRows = (clone $events)
            ->where('event_type', StorefrontEventType::PageView->value)
            ->selectRaw('source, COUNT(DISTINCT session_hash) AS sessions')
            ->groupBy('source')
            ->orderByDesc('sessions')
            ->limit(10)
            ->get();
        $sourceTotal = (int) $sourceRows->sum(fn ($row): int => (int) $row->sessions);

        $steps = [
            StorefrontEventType::PageView->value => 'Oturum',
            StorefrontEventType::ProductView->value => 'Ürün görüntüleme',
            StorefrontEventType::AddToCart->value => 'Sepete ekleme',
            StorefrontEventType::CheckoutStarted->value => 'Checkout başlangıcı',
            StorefrontEventType::Purchase->value => 'Satın alma',
        ];
        $sessionCount = (int) ($counts[StorefrontEventType::PageView->value] ?? 0);
        $previousCount = null;
        $funnel = collect($steps)->map(function (string $label, string $key) use ($counts, $sessionCount, &$previousCount): array {
            $count = (int) ($counts[$key] ?? 0);
            $conversion = $sessionCount > 0 ? round(min(100, ($count / $sessionCount) * 100), 1) : null;
            $stepRate = $previousCount !== null && $previousCount > 0
                ? round(min(100, ($count / $previousCount) * 100), 1)
                : ($key === StorefrontEventType::PageView->value && $count > 0 ? 100.0 : null);
            $previousCount = $count;

            return [
                'key' => $key,
                'label' => $label,
                'sessions' => $count,
                'conversion_rate' => $conversion,
                'step_rate' => $stepRate,
            ];
        })->values();

        return [
            'available' => $totalEvents > 0,
            'sessions' => $sessionCount,
            'total_events' => $totalEvents,
            'sources' => $sourceRows->map(fn ($row): array => [
                'source' => (string) $row->source,
                'sessions' => (int) $row->sessions,
                'share' => $sourceTotal > 0 ? round(((int) $row->sessions / $sourceTotal) * 100, 1) : 0.0,
            ])->values(),
            'funnel' => $funnel,
        ];
    }
}
