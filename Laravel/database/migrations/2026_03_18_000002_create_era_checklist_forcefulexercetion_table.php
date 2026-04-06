<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('era_checklist_forceful_exertions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assessment_id')
                ->constrained('era_assessments')
                ->cascadeOnDelete();

            $table->string('working_height_key');
            $table->string('working_height_label');
            $table->string('recommended_weight')->nullable();
            $table->string('current_weight')->nullable();
            $table->text('remarks')->nullable();
            $table->json('answers')->nullable();

            $table->timestamps();

            $table->unique(['assessment_id', 'working_height_key'], 'era_forceful_assessment_height_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('era_checklist_forceful_exertions');
    }
};
