<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('era_processes', function (Blueprint $table) {
            $table->id();

            // Foreign key to assessment
            $table->foreignId('assessment_id')
                ->constrained('era_assessments')
                ->onDelete('cascade');

            // Process name (e.g., Forklift Driver)
            $table->string('name');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('era_processes');
    }
};
