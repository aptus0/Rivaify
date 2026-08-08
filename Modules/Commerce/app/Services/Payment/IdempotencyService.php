<?php

namespace Modules\Commerce\Services\Payment;

use App\Core\Tenancy\CurrentStore;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Exceptions\Payment\IdempotencyInProgressException;
use Modules\Commerce\Exceptions\Payment\IdempotencyKeyConflictException;
use Modules\Commerce\Models\Payment\IdempotencyKey;

class IdempotencyService
{
    public function __construct(private readonly CurrentStore $currentStore) {}

    /**
     * @param  array<string, mixed>  $request
     */
    public function claim(string $key, string $operation, array $request = [], int $ttlMinutes = 1440): IdempotencyKey
    {
        $key = trim($key);
        $operation = trim($operation);
        if ($key === '' || $operation === '') {
            throw new \InvalidArgumentException('Idempotency key and operation are required.');
        }
        if ($ttlMinutes < 1) {
            throw new \InvalidArgumentException('Idempotency TTL must be at least one minute.');
        }

        $requestHash = hash('sha256', json_encode($request, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($key, $operation, $requestHash, $ttlMinutes) {
            $record = IdempotencyKey::query()
                ->where('key', $key)
                ->where('operation', $operation)
                ->lockForUpdate()
                ->first();
            if ($record === null) {
                return IdempotencyKey::query()->create([
                    'key' => $key,
                    'operation' => $operation,
                    'request_hash' => $requestHash,
                    'status' => 'processing',
                    'expires_at' => now()->addMinutes($ttlMinutes),
                ]);
            }

            if ($record->request_hash !== null && ! hash_equals($record->request_hash, $requestHash)) {
                throw new IdempotencyKeyConflictException('Idempotency key was already used with a different request.');
            }
            if ($record->status === 'completed' && $record->response !== null) {
                return $record;
            }
            if ($record->expires_at->isFuture()) {
                throw new IdempotencyInProgressException('An operation with this idempotency key is still processing.');
            }

            $record->update([
                'request_hash' => $requestHash,
                'status' => 'processing',
                'response' => null,
                'expires_at' => now()->addMinutes($ttlMinutes),
            ]);

            return $record->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function complete(IdempotencyKey $record, array $response): IdempotencyKey
    {
        return DB::transaction(function () use ($record, $response) {
            $record = IdempotencyKey::query()->lockForUpdate()->findOrFail($record->id);
            $record->update([
                'status' => 'completed',
                'response' => $response,
            ]);

            return $record->refresh();
        });
    }

    public function fail(IdempotencyKey $record, string $message): IdempotencyKey
    {
        return DB::transaction(function () use ($record, $message) {
            $record = IdempotencyKey::query()->lockForUpdate()->findOrFail($record->id);
            $record->update([
                'status' => 'failed',
                'response' => ['message' => $message],
            ]);

            return $record->refresh();
        });
    }
}