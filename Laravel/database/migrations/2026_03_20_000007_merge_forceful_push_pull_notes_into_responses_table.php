<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('era_forceful_push_pull_responses', 'note')) {
            Schema::table('era_forceful_push_pull_responses', function (Blueprint $table) {
                $table->text('note')->nullable()->after('not_applicable');
            });
        }

        if (Schema::hasTable('era_forceful_push_pull_notes')) {
            $notes = DB::table('era_forceful_push_pull_notes')
                ->select('response_id', 'note')
                ->get();

            foreach ($notes as $noteRow) {
                DB::table('era_forceful_push_pull_responses')
                    ->where('id', $noteRow->response_id)
                    ->update(['note' => $noteRow->note]);
            }

            Schema::dropIfExists('era_forceful_push_pull_notes');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('era_forceful_push_pull_notes')) {
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

        if (Schema::hasColumn('era_forceful_push_pull_responses', 'note')) {
            $responsesWithNotes = DB::table('era_forceful_push_pull_responses')
                ->whereNotNull('note')
                ->select('id', 'note', 'created_at', 'updated_at')
                ->get();

            foreach ($responsesWithNotes as $response) {
                DB::table('era_forceful_push_pull_notes')->updateOrInsert(
                    ['response_id' => $response->id],
                    [
                        'note' => $response->note,
                        'created_at' => $response->created_at,
                        'updated_at' => $response->updated_at,
                    ]
                );
            }

            Schema::table('era_forceful_push_pull_responses', function (Blueprint $table) {
                $table->dropColumn('note');
            });
        }
    }
};
