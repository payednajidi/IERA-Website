<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('era_checklist_answers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assessment_id')
                ->constrained('era_assessments')
                ->onDelete('cascade');

            $table->foreignId('task_id')
                ->constrained('era_tasks')
                ->onDelete('cascade');

            $table->foreignId('checklist_item_id')
                ->constrained('era_checklist_items')
                ->onDelete('cascade');

            $table->boolean('answer');

            $table->timestamps();

            $table->unique(['assessment_id', 'task_id', 'checklist_item_id']);
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('era_checklist_answers');
    }
};
