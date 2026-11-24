<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('metric_type')->index(); // comparisons, pages_processed, chat_queries, storage_bytes
            $table->unsignedBigInteger('quantity')->default(1);
            $table->timestamp('recorded_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'metric_type', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');
    }
};
