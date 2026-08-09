<?php

namespace App\Core\Internal\Models;

use App\Core\Shared\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['operation_case_id', 'staff_user_id', 'visibility', 'body'])]
class OperationCaseNote extends Model
{
    use HasUlid;

    public function case(): BelongsTo
    {
        return $this->belongsTo(OperationCase::class, 'operation_case_id');
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }
}
