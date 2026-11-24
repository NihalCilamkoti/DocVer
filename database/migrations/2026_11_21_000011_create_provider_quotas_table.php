<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_quotas', function (Blueprint $table) {
            $table->id();
            $table->string('provider_name')->index();
            $table->string('model')->index();
            $table->date('date')->index();
            $table->unsignedBigInteger('requests_used_today')->default(0);
            $table->unsignedBigInteger('input_tokens_used_today')->default(0);
            $table->unsignedBigInteger('output_tokens_used_today')->default(0);
            $table->unsignedBigInteger('total_tokens_used_today')->default(0);
            $table->unsignedBigInteger('daily_request_limit')->nullable();
            $table->unsignedBigInteger('daily_token_limit')->nullable();
            $table->unsignedBigInteger('requests_remaining')->nullable();
            $table->unsignedBigInteger('tokens_remaining')->nullable();
            $table->timestamp('last_reset_at')->nullable();
            $table->unsignedSmallInteger('last_error_status')->nullable();
            $table->text('last_error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider_name', 'model', 'date']);
            $table->index(['provider_name', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_quotas');
    }
};
