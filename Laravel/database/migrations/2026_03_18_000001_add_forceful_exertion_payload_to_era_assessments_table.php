<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('era_assessments', function (Blueprint $table) {
            $table->json('forceful_exertion_payload')->nullable()->after('breaks');
        });
    }

    public function down(): void
    {
        Schema::table('era_assessments', function (Blueprint $table) {
            $table->dropColumn('forceful_exertion_payload');
        });
    }
};
