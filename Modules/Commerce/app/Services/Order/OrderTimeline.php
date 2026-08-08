<?php

namespace Modules\Commerce\Services\Order;

use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Order\OrderEvent;

class OrderTimeline
{
    public function record(
        Order $order,
        string $type,
        string $message,
        ?string $actorType = null,
        ?int $actorId = null,
        array $metadata = [],
    ): OrderEvent {
        return $order->events()->create([
            'type' => $type,
            'message' => $message,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}