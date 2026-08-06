<?php

namespace Modules\Verification\Http\Controllers;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Verification\Actions\SubmitVerificationRequest;
use Modules\Verification\Actions\UploadVerificationDocument;
use Modules\Verification\Enums\DocumentType;

class VerificationOnboardingController extends Controller
{
    public function uploadDocument(Request $request, CurrentStore $currentStore, UploadVerificationDocument $action): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:tax_certificate,identity,signature_circular,business_license,other'],
            // 10MB cap, PDF/image only — these are compliance documents,
            // not arbitrary uploads.
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $store = $currentStore->store();
        $verificationRequest = $store->verificationRequests()->firstOrCreate([
            'merchant_id' => $store->merchant->id,
        ]);

        $document = $action->handle(
            $verificationRequest,
            $request->file('file'),
            DocumentType::from($validated['type']),
        );

        return response()->json(['data' => [
            'id' => $document->ulid,
            'type' => $document->type->value,
            'original_filename' => $document->original_filename,
        ]], 201);
    }

    public function submit(CurrentStore $currentStore, SubmitVerificationRequest $action): JsonResponse
    {
        $store = $currentStore->store();

        $verificationRequest = $action->handle($store->merchant, $store);

        return response()->json(['data' => [
            'id' => $verificationRequest->ulid,
            'status' => $verificationRequest->status->value,
        ]]);
    }
}
