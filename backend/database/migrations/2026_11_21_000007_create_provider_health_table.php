<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_health', function (Blueprint $table) {
            $table->id();
            $table->string('provider_name')->unique(); // e.g. openai, anthropic, gemini, ocr_engine
            $table->string('status')->default('healthy')->index(); // healthy, degraded, offline
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('last_checked_at')->index();
            $table->text('error_details')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_health');
    }
};
