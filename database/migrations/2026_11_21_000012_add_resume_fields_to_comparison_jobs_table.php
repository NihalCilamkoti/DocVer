<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comparison_jobs', function (Blueprint $table) {
            $table->json('completed_chunks')->nullable()->after('processing_progress');
            $table->string('last_completed_stage')->nullable()->after('completed_chunks');
        });
    }

    public function down(): void
    {
        Schema::table('comparison_jobs', function (Blueprint $table) {
            $table->dropColumn(['completed_chunks', 'last_completed_stage']);
        });
    }
};
