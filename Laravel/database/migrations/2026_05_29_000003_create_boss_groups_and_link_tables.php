<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the boss_groups pivot table and adds boss_group_id FKs to
 * boss_questionnaires and era_assessments so that many BOSS records
 * can be grouped together and tied to one ERA Assessment.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Boss groups table — a lightweight container record
        Schema::create('boss_groups', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        // 2. Link each BOSS questionnaire to a group
        Schema::table('boss_questionnaires', function (Blueprint $table) {
            $table->unsignedBigInteger('boss_group_id')->nullable()->after('id');
            $table->foreign('boss_group_id')
                  ->references('id')
                  ->on('boss_groups')
                  ->onDelete('set null');
        });

        // 3. Link each ERA assessment to a group
        Schema::table('era_assessments', function (Blueprint $table) {
            $table->unsignedBigInteger('boss_group_id')->nullable()->after('boss_questionnaire_id');
            $table->foreign('boss_group_id')
                  ->references('id')
                  ->on('boss_groups')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('era_assessments', function (Blueprint $table) {
            $table->dropForeign(['boss_group_id']);
            $table->dropColumn('boss_group_id');
        });

        Schema::table('boss_questionnaires', function (Blueprint $table) {
            $table->dropForeign(['boss_group_id']);
            $table->dropColumn('boss_group_id');
        });

        Schema::dropIfExists('boss_groups');
    }
};
