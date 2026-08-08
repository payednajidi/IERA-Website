<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('era_checklist_answers', function (Blueprint $table) {
            $table->text('remarks')->nullable()->after('answer');
        });
    }

    public function down(): void
    {
        Schema::table('era_checklist_answers', function (Blueprint $table) {
            $table->dropColumn('remarks');
        });
    }
};
