<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comparison_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comparison_job_id')->unique()->constrained('comparison_jobs')->cascadeOnDelete();
            $table->unsignedInteger('total_differences')->default(0);
            $table->unsignedInteger('high_risk_count')->default(0);
            $table->unsignedInteger('medium_risk_count')->default(0);
            $table->unsignedInteger('low_risk_count')->default(0);
            $table->unsignedInteger('matching_clauses_count')->default(0);
            $table->unsignedInteger('missing_clauses_count')->default(0);
            $table->unsignedInteger('modified_clauses_count')->default(0);
            $table->unsignedInteger('numerical_discrepancies_count')->default(0);
            $table->unsignedInteger('contradictions_count')->default(0);
            $table->text('executive_summary')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comparison_reports');
    }
};
