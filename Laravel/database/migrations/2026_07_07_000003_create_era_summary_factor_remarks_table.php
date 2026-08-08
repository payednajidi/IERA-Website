<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('era_summary_factor_remarks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assessment_id');
            $table->string('factor_key', 64);
            $table->text('remarks');
            $table->timestamps();

            $table->unique(['assessment_id', 'factor_key']);
            $table->foreign('assessment_id')->references('id')->on('era_assessments')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('era_summary_factor_remarks');
    }
};
