<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('era_checklist_forceful_exertions', function (Blueprint $table) {
            // Allows marking an entire lifting/lowering section as Not Applicable for a given task.
            // Consistent with the not_applicable field on push_pull, carrying, and manual_summary tables.
            $table->boolean('not_applicable')->default(false)->after('answer');
        });
    }

    public function down(): void
    {
        Schema::table('era_checklist_forceful_exertions', function (Blueprint $table) {
            $table->dropColumn('not_applicable');
        });
    }
};
