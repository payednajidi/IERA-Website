<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boss_questionnaires', function (Blueprint $table) {
            // Date range: replaces the single `date` column for multi-day activities.
            // The original `date` column is kept for backward compatibility with old records.
            $table->date('date_start')->nullable()->after('date');
            $table->date('date_end')->nullable()->after('date_start');

            // Process: higher-level work area (parent of job_task), syncs to ERA process name.
            $table->string('process')->nullable()->after('company');
        });
    }

    public function down(): void
    {
        Schema::table('boss_questionnaires', function (Blueprint $table) {
            $table->dropColumn(['date_start', 'date_end', 'process']);
        });
    }
};
