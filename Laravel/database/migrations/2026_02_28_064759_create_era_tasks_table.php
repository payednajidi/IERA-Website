<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('era_tasks', function (Blueprint $table) {
            $table->id();

            // Relationship to process
            $table->foreignId('process_id')
                ->constrained('era_processes')
                ->onDelete('cascade');

            // Task main title (bold text)
            $table->string('title');

            // Task subtitle / description (after dash)
            $table->string('description')->nullable();

            // Worker activities (bullet / numbered content)
            $table->text('worker_activities')->nullable();

            // Order number inside process
            $table->integer('row_number');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('era_tasks');
    }
};
