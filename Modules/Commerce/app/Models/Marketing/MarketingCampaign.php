<?php

namespace Modules\Commerce\Models\Marketing;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'channel', 'objective', 'status', 'budget', 'currency', 'starts_at', 'ends_at', 'content'])]
class MarketingCampaign extends Model
{
    use BelongsToStore, HasUlid;
    protected function casts(): array { return ['budget' => 'decimal:2', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'content' => 'array']; }
}
