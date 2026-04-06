<?php

namespace Database\Seeders;

use App\Models\EraChecklistItem;
use App\Models\EraChecklistTemplate;
use Illuminate\Database\Seeder;

class EraForcefulReferenceTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $template = EraChecklistTemplate::firstOrCreate(
            ['name' => 'ERGONOMICS RISK FACTORS: FORCEFUL EXERTION'],
            ['order' => 99]
        );

        $rows = [
            [
                'body_part' => 'Repetitive handling',
                'description' => 'Once or twice per minutes',
                'max_duration' => 'Weight should be reduced by 30%',
                'order' => 1,
            ],
            [
                'body_part' => 'Repetitive handling',
                'description' => 'Five to eight times per minute',
                'max_duration' => 'Weight should be reduced by 50%',
                'order' => 2,
            ],
            [
                'body_part' => 'Repetitive handling',
                'description' => 'More than 12 times per minute',
                'max_duration' => 'Weight should be reduced by 80%',
                'order' => 3,
            ],
            [
                'body_part' => 'Twisted body posture',
                'description' => 'Twists body 45 degrees',
                'max_duration' => 'Weight should be reduced by 10%',
                'order' => 4,
            ],
            [
                'body_part' => 'Twisted body posture',
                'description' => 'Twists body 90 degrees',
                'max_duration' => 'Weight should be reduced by 20%',
                'order' => 5,
            ],
        ];

        foreach ($rows as $row) {
            EraChecklistItem::updateOrCreate(
                [
                    'checklist_template_id' => $template->id,
                    'order' => $row['order'],
                ],
                [
                    'body_part' => $row['body_part'],
                    'description' => $row['description'],
                    'max_duration' => $row['max_duration'],
                ]
            );
        }
    }
}
