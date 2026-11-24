<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comparison_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comparison_report_id')->constrained('comparison_reports')->cascadeOnDelete();
            $table->string('type')->index(); // matching, missing, modified, numerical, contradiction
            $table->string('risk_level')->default('none')->index(); // high, medium, low, none
            $table->string('category')->index();
            $table->string('title');
            $table->unsignedInteger('doc_a_page')->nullable();
            $table->unsignedInteger('doc_b_page')->nullable();
            $table->text('explanation');
            $table->text('significance')->nullable();
            $table->json('metrics')->nullable(); // e.g. variance percentage, valueA, valueB
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();

            $table->index(['comparison_report_id', 'risk_level']);
            $table->index(['comparison_report_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comparison_findings');
    }
};
