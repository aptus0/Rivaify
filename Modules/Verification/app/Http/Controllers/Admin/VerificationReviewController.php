<?php

namespace Modules\Verification\Http\Controllers\Admin;

use App\Core\Tenancy\Scopes\StoreScope;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Verification\Actions\ApproveVerificationRequest;
use Modules\Verification\Actions\RejectVerificationRequest;
use Modules\Verification\Enums\VerificationStatus;
use Modules\Verification\Models\VerificationRequest;

/**
 * Rivaify Admin is cross-tenant by nature — every query here deliberately
 * bypasses StoreScope (see that class's docblock) rather than running
 * through store.context, which this controller's routes never apply.
 */
class VerificationReviewController extends Controller
{
    public function index(): JsonResponse
    {
        $requests = VerificationRequest::withoutGlobalScope(StoreScope::class)
            ->with(['merchant', 'store', 'documents'])
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
            ->where('ulid', $ulid)
            ->firstOrFail();
    }

    private function present(VerificationRequest $request, bool $withDocumentUrls = false): array
    {
        return [
            'id' => $request->ulid,
            'status' => $request->status->value,
            'submitted_at' => $request->submitted_at?->toIso8601String(),
            'store' => [
                'name' => $request->store->name,
                'slug' => $request->store->slug,
            ],
            'merchant' => [
                'type' => $request->merchant->type->value,
            ],
            'documents' => $request->documents->map(fn ($d) => [
                'id' => $d->ulid,
                'type' => $d->type->value,
                'original_filename' => $d->original_filename,
                'url' => $withDocumentUrls ? $d->signedUrl() : null,
            ]),
        ];
    }
}
