<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_evaluation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_id')->unique();
            $table->string('provider')->default('groq');
            $table->string('model')->default('openai/gpt-oss-20b');
            $table->string('category')->default('all');
            $table->integer('total_tests')->default(0);
            $table->integer('passed_tests')->default(0);
            $table->integer('failed_tests')->default(0);
            $table->float('overall_accuracy')->default(0.0);
            $table->float('instruction_accuracy')->default(0.0);
            $table->float('citation_accuracy')->default(0.0);
            $table->float('grounding_accuracy')->default(0.0);
            $table->float('hallucination_rate')->default(0.0);
            $table->integer('avg_latency_ms')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->integer('duration_ms')->default(0);
            $table->string('status')->default('completed'); // 'running', 'completed', 'failed'
            $table->json('metrics')->nullable();
            $table->timestamps();

            $table->index('provider');
            $table->index('created_at');
        });

        Schema::create('ai_evaluation_results', function (Blueprint $table) {
            $table->id();
            $table->string('run_id');
            $table->string('test_id');
            $table->string('category');
            $table->text('input');
            $table->string('expected_task')->nullable();
            $table->json('expected_answer')->nullable();
            $table->longText('actual_output')->nullable();
            $table->float('score')->default(0.0);
            $table->boolean('passed')->default(false);
            $table->string('failure_category')->nullable();
            $table->integer('latency_ms')->default(0);
            $table->integer('tokens_used')->default(0);
            $table->string('provider')->default('groq');
            $table->string('model')->default('default');
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index('run_id');
            $table->index('test_id');
            $table->index('category');
            $table->index('passed');
            $table->index('failure_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_evaluation_results');
        Schema::dropIfExists('ai_evaluation_runs');
    }
};
