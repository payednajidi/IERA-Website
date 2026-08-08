<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boss_questionnaires', function (Blueprint $table) {
            // The legacy single `date` column is superseded by `date_start` / `date_end`.
            // All new records use the range columns; existing data has already been migrated.
            $table->dropColumn('date');
        });
    }

    public function down(): void
    {
        Schema::table('boss_questionnaires', function (Blueprint $table) {
            $table->date('date')->nullable()->after('staff_id');
        });
    }
};
