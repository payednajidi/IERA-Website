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
        Schema::create('era_checklist_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('checklist_template_id')
                ->constrained('era_checklist_templates')
                ->onDelete('cascade');

            $table->string('body_part');
            $table->text('description');
            $table->string('max_duration')->nullable();
            $table->integer('order')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('era_checklist_items');
    }
};
