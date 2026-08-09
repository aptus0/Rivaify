<?php

namespace Modules\Verification\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Verification\Enums\DocumentStatus;
use Modules\Verification\Enums\DocumentType;
use Modules\Verification\Models\VerificationDocument;
use Modules\Verification\Models\VerificationRequest;

class UploadVerificationDocument
{
    private const DISK = 'r2';

    public function handle(VerificationRequest $verificationRequest, UploadedFile $file, DocumentType $type): VerificationDocument
    {
        $path = Storage::disk(self::DISK)->putFile(
            "verification-requests/{$verificationRequest->ulid}",
            $file,
        );

        return $verificationRequest->documents()->create([
            'type' => $type,
            'status' => DocumentStatus::Pending,
            'storage_disk' => self::DISK,
            'storage_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'size_bytes' => $file->getSize(),
        ]);
    }
}
