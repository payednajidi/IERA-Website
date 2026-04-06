<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('era_forceful_push_pull_notes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('response_id')
                ->constrained('era_forceful_push_pull_responses')
                ->cascadeOnDelete();

            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique('response_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('era_forceful_push_pull_notes');
    }
};
