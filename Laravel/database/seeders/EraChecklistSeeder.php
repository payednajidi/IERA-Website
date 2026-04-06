<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EraChecklistTemplate;
use App\Models\EraChecklistItem;

class EraChecklistSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | TEMPLATE 1
        |--------------------------------------------------------------------------
        */
        $template1 = EraChecklistTemplate::firstOrCreate(
            ['name' => 'ERGONOMICS RISK FACTORS: AWKWARD POSTURE'],
            ['order' => 1]
        );

        $items1 = [

            // SHOULDERS
            ['body_part' => 'Shoulders', 'description' => 'Work with hand above the head OR the elbow above the shoulder', 'max_duration' => 'More than 2 hours per day', 'order' => 1],
            ['body_part' => 'Shoulders', 'description' => 'Work with shoulder raised', 'max_duration' => 'More than 2 hours per day', 'order' => 2],
            ['body_part' => 'Shoulders', 'description' => 'Work repetitively by raising the hand above the head OR the elbow above the shoulder more than once per minute', 'max_duration' => 'More than 2 hours per day', 'order' => 3],

            // HEAD
            ['body_part' => 'Head', 'description' => 'Work with head bent downwards more than 45 degrees', 'max_duration' => 'More than 2 hours per day', 'order' => 4],
            ['body_part' => 'Head', 'description' => 'Work with head bent backwards', 'max_duration' => 'More than 2 hours per day', 'order' => 5],
            ['body_part' => 'Head', 'description' => 'Work with head bent sideways', 'max_duration' => 'More than 2 hours per day', 'order' => 6],

            // BACK
            ['body_part' => 'Back', 'description' => 'Work with back bent forward more than 30 degrees OR bent sideways', 'max_duration' => 'More than 2 hours per day', 'order' => 7],
            ['body_part' => 'Back', 'description' => 'Work with body twisted', 'max_duration' => 'More than 2 hours per day', 'order' => 8],

            // HAND / ELBOW / WRIST
            ['body_part' => 'Hand / Elbow / Wrist', 'description' => 'Work with wrist flexion OR extension OR radial deviation more than 15 degrees', 'max_duration' => 'More than 2 hours per day', 'order' => 9],
            ['body_part' => 'Hand / Elbow / Wrist', 'description' => 'Work with arm abduction sideways', 'max_duration' => 'More than 4 hours per day', 'order' => 10],
            ['body_part' => 'Hand / Elbow / Wrist', 'description' => 'Work with arm forward more than 45 degrees OR arm backward more than 20 degrees', 'max_duration' => 'More than 2 hours per day', 'order' => 11],

            // LEG / KNEES
            ['body_part' => 'Leg / Knees', 'description' => 'Work in a squat position', 'max_duration' => 'More than 2 hours per day', 'order' => 12],
            ['body_part' => 'Leg / Knees', 'description' => 'Work in a kneeling position', 'max_duration' => 'More than 2 hours per day', 'order' => 13],
        ];

        foreach ($items1 as $item) {
            EraChecklistItem::updateOrCreate(
                [
                    'checklist_template_id' => $template1->id,
                    'order' => $item['order'],
                ],
                [
                    'body_part' => $item['body_part'],
                    'description' => $item['description'],
                    'max_duration' => $item['max_duration'],
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | TEMPLATE 2
        |--------------------------------------------------------------------------
        */
        $template2 = EraChecklistTemplate::firstOrCreate(
            ['name' => 'ERGONOMICS RISK FACTORS: STATIC AND SUSTAINED WORK POSTURE'],
            ['order' => 2]
        );

        $items2 = [

            [
                'body_part' => 'Trunk / Head / Neck / Arm / Wrist',
                'description' => 'Work in a static awkward position as in Table 1 (awkward posture table)',
                'max_duration' => 'Duration as per Table 1 (please refer duration in awkward posture table)',
                'order' => 1
            ],

            [
                'body_part' => 'Leg / Knees',
                'description' => 'Work in a standing position with minimal leg movement',
                'max_duration' => 'More than 2 hours continuously',
                'order' => 2
            ],

            [
                'body_part' => 'Leg / Knees',
                'description' => 'Work in static seated position with minimal movement',
                'max_duration' => 'More than 30 minutes continuously',
                'order' => 3
            ],
        ];

        foreach ($items2 as $item) {
            EraChecklistItem::updateOrCreate(
                [
                    'checklist_template_id' => $template2->id,
                    'order' => $item['order'],
                ],
                [
                    'body_part' => $item['body_part'],
                    'description' => $item['description'],
                    'max_duration' => $item['max_duration'],
                ]
            );
        }
    }
}
