<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Verification\Enums\DocumentStatus;
use Modules\Verification\Enums\DocumentType;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_documents', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('verification_request_id')->constrained('verification_requests')->cascadeOnDelete();

            $table->string('type')->default(DocumentType::Other->value);
            $table->string('status')->default(DocumentStatus::Pending->value);

            // Files live in Cloudflare R2 (private disk, brief §10) — never
            // on the app server. storage_path is the R2 object key, not a
            // local filesystem path.
            $table->string('storage_disk')->default('r2');
            $table->string('storage_path');
            $table->string('original_filename')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            $table->timestamps();

            $table->index('verification_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_documents');
    }
};
