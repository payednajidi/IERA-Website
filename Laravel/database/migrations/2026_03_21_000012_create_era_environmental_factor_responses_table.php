<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('era_environmental_factor_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assessment_id');
            $table->unsignedBigInteger('task_id');
            $table->string('row_key');
            $table->boolean('answer')->default(false);
            $table->boolean('not_applicable')->default(false);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('assessment_id')
                ->references('id')
                ->on('era_assessments')
                ->onDelete('cascade');
            $table->foreign('task_id')
                ->references('id')
                ->on('era_tasks')
                ->onDelete('cascade');

            $table->unique(
                ['assessment_id', 'task_id', 'row_key'],
                'era_environmental_factor_unique_row'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('era_environmental_factor_responses');
    }
};
