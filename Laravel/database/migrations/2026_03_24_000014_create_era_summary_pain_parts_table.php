<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('era_summary_pain_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')
                ->constrained('era_assessments')
                ->onDelete('cascade');
            $table->foreignId('task_id')
                ->constrained('era_tasks')
                ->onDelete('cascade');
            $table->string('body_part', 100);
            $table->timestamps();

            $table->unique(
                ['assessment_id', 'task_id', 'body_part'],
                'era_summary_pain_parts_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('era_summary_pain_parts');
    }
};

