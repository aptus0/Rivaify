<?php

namespace Modules\Commerce\Models\Order;

use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['next_number'])]
class OrderSequence extends Model
{
    use BelongsToStore;
}