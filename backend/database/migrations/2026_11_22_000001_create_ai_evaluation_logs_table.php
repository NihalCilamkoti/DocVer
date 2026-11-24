<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_evaluation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->foreignId('comparison_job_id')->nullable()->constrained('comparison_jobs')->nullOnDelete();
            $table->string('conversation_id')->nullable()->index();
            $table->string('task_type')->index();
            $table->string('prompt_version', 30)->default('v2.4.0');
            $table->text('input_prompt');
            $table->json('conversation_context')->nullable();
            $table->json('document_references')->nullable();
            $table->json('expected_characteristics')->nullable();
            $table->longText('actual_response');
            $table->string('provider', 50)->default('groq');
            $table->string('model', 100)->default('qwen');
            $table->boolean('is_successful')->default(true)->index();
            $table->string('error_category', 100)->nullable()->index();
            $table->float('grounding_score')->nullable();
            $table->string('user_rating', 20)->nullable(); // up, down
            $table->text('user_feedback')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['task_type', 'is_successful']);
            $table->index(['created_at', 'error_category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_evaluation_logs');
    }
};
