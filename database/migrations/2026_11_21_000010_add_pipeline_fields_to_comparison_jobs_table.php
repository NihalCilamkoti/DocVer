<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comparison_jobs', function (Blueprint $table) {
            $table->unsignedTinyInteger('processing_progress')->default(0)->after('status');
            $table->string('current_stage')->nullable()->after('processing_progress');
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('comparison_jobs', function (Blueprint $table) {
            $table->dropColumn(['processing_progress', 'current_stage', 'cancelled_at']);
        });
    }
};
