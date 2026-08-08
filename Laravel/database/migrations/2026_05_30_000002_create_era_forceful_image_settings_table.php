<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the per-assessment Not Applicable state for the two reference images
 * shown in Step 3 (Forceful Exertion):
 *   • Forceful Exertion.png  (Figure 3.A – Manual Handling)
 *   • Seated Position.png    (Figure 3.B – Handling in Seated Position)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('era_forceful_image_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')
                  ->unique()
                  ->constrained('era_assessments')
                  ->cascadeOnDelete();
            $table->boolean('forceful_image_na')->default(false);
            $table->boolean('seated_image_na')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('era_forceful_image_settings');
    }
};
