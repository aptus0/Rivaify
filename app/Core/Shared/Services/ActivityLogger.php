<?php

namespace App\Core\Shared\Services;

use App\Core\Shared\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    public function log(string $event, array $properties = [], ?Model $subject = null, ?int $storeId = null, ?int $userId = null): ActivityLog
    {
        return ActivityLog::query()->create([
            'event' => $event,
            'properties' => $properties,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'store_id' => $storeId,
            'user_id' => $userId ?? auth()->id(),
        ]);
    }
}
