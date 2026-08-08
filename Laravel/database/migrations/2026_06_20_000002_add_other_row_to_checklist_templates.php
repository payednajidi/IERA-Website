<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $templates = DB::table('era_checklist_templates')->orderBy('id')->get();

        foreach ($templates as $template) {
            $alreadyExists = DB::table('era_checklist_items')
                ->where('checklist_template_id', $template->id)
                ->where('body_part', 'Other')
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            $maxOrder = DB::table('era_checklist_items')
                ->where('checklist_template_id', $template->id)
                ->max('order') ?? 0;

            DB::table('era_checklist_items')->insert([
                'checklist_template_id' => $template->id,
                'body_part'             => 'Other',
                'description'           => 'Other',
                'max_duration'          => null,
                'order'                 => $maxOrder + 1,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('era_checklist_items')
            ->where('body_part', 'Other')
            ->where('description', 'Other')
            ->delete();
    }
};
