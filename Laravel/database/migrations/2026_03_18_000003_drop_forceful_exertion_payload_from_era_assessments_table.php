<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('era_assessments', 'forceful_exertion_payload')) {
            Schema::table('era_assessments', function (Blueprint $table) {
                $table->dropColumn('forceful_exertion_payload');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('era_assessments', 'forceful_exertion_payload')) {
            Schema::table('era_assessments', function (Blueprint $table) {
                $table->json('forceful_exertion_payload')->nullable()->after('breaks');
            });
        }
    }
};
