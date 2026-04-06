<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('era_forceful_push_pull_responses', 'note')) {
            Schema::table('era_forceful_push_pull_responses', function (Blueprint $table) {
                $table->dropColumn('note');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('era_forceful_push_pull_responses', 'note')) {
            Schema::table('era_forceful_push_pull_responses', function (Blueprint $table) {
                $table->text('note')->nullable()->after('not_applicable');
            });
        }
    }
};
