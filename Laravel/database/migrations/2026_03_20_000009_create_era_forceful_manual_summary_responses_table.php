<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('era_forceful_manual_summary_responses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assessment_id')
                ->constrained('era_assessments')
                ->cascadeOnDelete();

            $table->foreignId('task_id')
                ->constrained('era_tasks')
                ->cascadeOnDelete();

            $table->string('row_key');
            $table->boolean('answer')->default(false);
            $table->boolean('not_applicable')->default(false);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(
                ['assessment_id', 'task_id', 'row_key'],
                'era_forceful_manual_summary_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('era_forceful_manual_summary_responses');
    }
};
