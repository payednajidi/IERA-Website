<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('era_assessments', function (Blueprint $table) {
            $table->unsignedBigInteger('boss_questionnaire_id')->nullable()->after('id');
            $table->foreign('boss_questionnaire_id')
                  ->references('id')
                  ->on('boss_questionnaires')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('era_assessments', function (Blueprint $table) {
            $table->dropForeign(['boss_questionnaire_id']);
            $table->dropColumn('boss_questionnaire_id');
        });
    }
};
