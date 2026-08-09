<?php

namespace Modules\Verification\Http\Controllers\Admin;

use App\Core\Shared\Services\ActivityLogger;
use App\Core\Tenancy\Scopes\StoreScope;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Verification\Actions\ApproveVerificationRequest;
use Modules\Verification\Actions\RejectVerificationRequest;
use Modules\Verification\Enums\VerificationStatus;
use Modules\Verification\Models\VerificationDocument;
use Modules\Verification\Models\VerificationRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rivaify Admin is cross-tenant by nature — every query here deliberately
 * bypasses StoreScope (see that class's docblock) rather than running
 * through store.context, which this controller's routes never apply.
 */
class VerificationReviewController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function index(): JsonResponse
    {
        $requests = VerificationRequest::withoutGlobalScope(StoreScope::class)
            ->with(['merchant.businessProfile', 'store', 'documents'])
            ->where('status', VerificationStatus::Pending)
            ->orderBy('submitted_at')
            ->get();

        return response()->json(['data' => $requests->map(fn (VerificationRequest $r) => $this->present($r))]);
    }

    public function show(string $verificationRequest): JsonResponse
    {
        $request = $this->findOrFail($verificationRequest);

        return response()->json(['data' => $this->present($request, withDocumentUrls: true)]);
    }

    public function approve(string $verificationRequest, Request $httpRequest, ApproveVerificationRequest $action): JsonResponse
    {
        $record = $this->findOrFail($verificationRequest);

        $action->handle($record, $httpRequest->user());

        return response()->json(['data' => $this->present($record->fresh())]);
    }

    public function revealSensitiveField(string $verificationRequest, Request $httpRequest): JsonResponse
    {
        $validated = $httpRequest->validate([
            'field' => ['required', 'string', 'in:tax_number,registration_number'],
        ]);

        $record = $this->findOrFail($verificationRequest);
        $value = match ($validated['field']) {
            'tax_number' => $record->merchant->taxProfile?->tax_number,
            'registration_number' => $record->merchant->businessProfile?->registration_number,
        };

        $this->activity->log('internal.sensitive_field_revealed', [
            'field' => $validated['field'],
            'verification_request' => $record->ulid,
            'ip' => $httpRequest->ip(),
            'user_agent' => Str::limit((string) $httpRequest->userAgent(), 500, ''),
        ], $record, $record->store_id, $httpRequest->user()?->id);

        return response()->json(['data' => [
            'field' => $validated['field'],
            'value' => $value,
        ]]);
    }

    public function viewDocument(string $verificationRequest, string $document, Request $httpRequest): StreamedResponse
    {
        $record = $this->findOrFail($verificationRequest);
        $documentRecord = $record->documents
            ->first(fn (VerificationDocument $candidate): bool => $candidate->ulid === $document);

        abort_if($documentRecord === null, 404);

        $disk = Storage::disk($documentRecord->storage_disk);
        abort_unless($disk->exists($documentRecord->storage_path), 404);

        $this->activity->log('internal.verification_document_viewed', [
            'document' => $documentRecord->ulid,
            'type' => $documentRecord->type->value,
            'verification_request' => $record->ulid,
            'ip' => $httpRequest->ip(),
            'user_agent' => Str::limit((string) $httpRequest->userAgent(), 500, ''),
        ], $documentRecord, $record->store_id, $httpRequest->user()?->id);

        $stream = $disk->readStream($documentRecord->storage_path);
        abort_if($stream === false, 404);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $disk->mimeType($documentRecord->storage_path) ?: 'application/octet-stream',
            'Content-Length' => (string) ($disk->size($documentRecord->storage_path) ?: $documentRecord->size_bytes),
            'Content-Disposition' => 'inline; filename="'.$this->safeFilename($documentRecord->original_filename).'"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function reject(string $verificationRequest, Request $httpRequest, RejectVerificationRequest $action): JsonResponse
    {
        $validated = $httpRequest->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $record = $this->findOrFail($verificationRequest);

        $action->handle($record, $httpRequest->user(), $validated['reason']);

        return response()->json(['data' => $this->present($record->fresh())]);
    }

    private function findOrFail(string $ulid): VerificationRequest
    {
        return VerificationRequest::withoutGlobalScope(StoreScope::class)
            ->with(['merchant.owner', 'merchant.businessProfile.addresses', 'merchant.taxProfile', 'store', 'documents'])
            ->where('ulid', $ulid)
            ->firstOrFail();
    }

    private function present(VerificationRequest $request, bool $withDocumentUrls = false): array
    {
        $business = $request->merchant->businessProfile;
        $tax = $request->merchant->taxProfile;

        return [
            'id' => $request->ulid,
            'status' => $request->status->value,
            'submitted_at' => $request->submitted_at?->toIso8601String(),
            'rejection_reason' => $request->rejection_reason,
            'store' => [
                'name' => $request->store->name,
                'slug' => $request->store->slug,
            ],
            'merchant' => [
                'type' => $request->merchant->type->value,
                'owner' => $withDocumentUrls && $request->merchant->relationLoaded('owner') ? [
                    'name' => $request->merchant->owner->name,
                    'email' => $request->merchant->owner->email,
                ] : null,
            ],
            'business' => $business ? [
                'legal_name' => $business->legal_name,
                'trade_name' => $business->trade_name,
                'registration_number' => $this->maskSensitive($business->registration_number),
                'contact_email' => $business->contact_email,
                'contact_phone' => $business->contact_phone,
                'address' => $withDocumentUrls ? $business->addresses->map(fn ($a) => [
                    'line1' => $a->line1,
                    'line2' => $a->line2,
                    'city' => $a->city,
                    'state' => $a->state,
                    'postal_code' => $a->postal_code,
                    'country_code' => $a->country_code,
                ])->first() : null,
            ] : null,
            'tax' => $tax ? [
                'tax_office' => $tax->tax_office,
                'tax_number' => $this->maskSensitive($tax->tax_number),
                'legal_entity_name' => $tax->legal_entity_name,
            ] : null,
            'documents' => $request->documents->map(fn ($d) => [
                'id' => $d->ulid,
                'type' => $d->type->value,
                'original_filename' => $d->original_filename,
                'size_bytes' => $d->size_bytes,
                'view_url' => $withDocumentUrls
                    ? "/api/admin/verification-requests/{$request->ulid}/documents/{$d->ulid}/view"
                    : null,
            ]),
        ];
    }

    private function maskSensitive(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $normalized = preg_replace('/\s+/', '', $value) ?: $value;
        $suffix = Str::substr($normalized, -4);

        return str_repeat('*', max(6, Str::length($normalized) - 4)).$suffix;
    }

    private function safeFilename(?string $filename): string
    {
        $filename = $filename ?: 'verification-document';
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'verification-document';

        return trim($filename, '.-') ?: 'verification-document';
    }
}
