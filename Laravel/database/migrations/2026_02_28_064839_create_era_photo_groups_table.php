<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('era_photo_groups', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assessment_id')
                ->constrained('era_assessments')
                ->cascadeOnDelete();

            // 🔥 NEW: link photo group to specific task
            $table->foreignId('task_id')
                ->nullable()
                ->constrained('era_tasks')
                ->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('era_photo_groups');
    }
};
