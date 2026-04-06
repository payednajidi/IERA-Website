<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('era_forceful_push_pull_responses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assessment_id')
                ->constrained('era_assessments')
                ->cascadeOnDelete();

            $table->foreignId('task_id')
                ->constrained('era_tasks')
                ->cascadeOnDelete();

            $table->string('activity_key');
            $table->boolean('answer')->default(false);
            $table->boolean('not_applicable')->default(false);

            $table->timestamps();

            $table->unique(
                ['assessment_id', 'task_id', 'activity_key'],
                'era_forceful_push_pull_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('era_forceful_push_pull_responses');
    }
};
