<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comparison_job_id')->nullable()->constrained('comparison_jobs')->cascadeOnDelete();
            $table->enum('slot', ['A', 'B']);
            $table->string('original_name');
            $table->string('storage_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('page_count')->nullable();
            $table->char('checksum_sha256', 64)->index();
            $table->boolean('is_sanitized')->default(false);
            $table->timestamp('expires_at')->index();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['comparison_job_id', 'slot']);
            $table->index(['workspace_id', 'checksum_sha256']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_metadata');
    }
};
