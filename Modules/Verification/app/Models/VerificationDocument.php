<?php

namespace Modules\Verification\Models;

use App\Core\Shared\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Modules\Verification\Enums\DocumentStatus;
use Modules\Verification\Enums\DocumentType;

/**
 * No store_id column of its own (brief's schema doesn't give it one) — it
 * is reached only through its parent VerificationRequest, which is already
 * store-scoped. Scoping here too would be redundant and would require a
 * denormalized store_id just to satisfy BelongsToStore.
 */
#[Fillable(['type', 'status', 'storage_disk', 'storage_path', 'original_filename', 'size_bytes'])]
class VerificationDocument extends Model
{
    use HasFactory, HasUlid;

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
        ];
    }

    public function verificationRequest(): BelongsTo
    {
        return $this->belongsTo(VerificationRequest::class);
    }

    /**
     * Documents are private (brief §10) — the admin review screen requests
     * a short-lived signed URL on demand rather than the file ever being
     * publicly reachable.
     */
    public function signedUrl(int $minutes = 10): string
    {
        return Storage::disk($this->storage_disk)->temporaryUrl(
            $this->storage_path,
            now()->addMinutes($minutes),
        );
    }
}
