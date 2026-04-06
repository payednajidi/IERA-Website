<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('era_assessments', function (Blueprint $table) {
            $table->id();
            $table->string('assessor_name');
            $table->string('assessment_date');
            $table->string('department');
            $table->string('working_hours')->nullable();
            $table->string('breaks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('era_assessments');
    }
};
