<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('era_result_descriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assessment_id');
            $table->unsignedBigInteger('task_id');
            $table->string('risk_factor_key');
            $table->json('bullets');
            $table->timestamps();

            $table->unique(['assessment_id', 'task_id', 'risk_factor_key'], 'erd_unique');
            $table->foreign('assessment_id')->references('id')->on('era_assessments')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('era_result_descriptions');
    }
};
