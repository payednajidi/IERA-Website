<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boss_questionnaires', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('staff_id', 100)->nullable();
            $table->date('date')->nullable();
            $table->string('department')->nullable();
            $table->string('company')->nullable();
            $table->string('job_task')->nullable();

            // 12 body regions (A–L), each with selected flag, due_to_work, and responses JSON
            foreach ([
                'neck', 'shoulder', 'upper_back', 'lower_back',
                'upper_arm', 'elbow', 'lower_arm', 'hand_wrist',
                'thigh', 'knee', 'lower_leg', 'ankle_foot',
            ] as $region) {
                $table->boolean("{$region}_selected")->default(false);
                $table->boolean("{$region}_due_to_work")->nullable();
                $table->json("{$region}_responses")->nullable();
            }

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boss_questionnaires');
    }
};
