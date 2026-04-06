<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('era_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_group_id')
                ->constrained('era_photo_groups')
                ->onDelete('cascade');

            $table->string('file_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('era_photos');
    }
};
