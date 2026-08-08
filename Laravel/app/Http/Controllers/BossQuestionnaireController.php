<?php

namespace App\Http\Controllers;

use App\Models\BossQuestionnaire;
use App\Models\EraAssessment;
use Illuminate\Http\Request;

class BossQuestionnaireController extends Controller
{
    private const SEVERITY_VALUES = ['no_very_mild', 'discomfort', 'pain_able', 'pain_unable'];
    private const FREQUENCY_VALUES = [
        'once_a_year', 'few_times_a_year', 'once_a_month', 'few_times_a_month',
        'once_a_week', 'few_times_a_week', 'everyday',
    ];
    private const REGIONS = [
        'neck', 'shoulder', 'upper_back', 'lower_back',
        'upper_arm', 'elbow', 'lower_arm', 'hand_wrist',
        'thigh', 'knee', 'lower_leg', 'ankle_foot',
    ];

    public function store(Request $request)
    {
        $rules = [
            'name'       => 'required|string|max:255',
            'staff_id'   => 'nullable|string|max:100',
            // Legacy single-date field — kept nullable for old records; new submissions omit it.
            'date'       => 'nullable|date',
            // Date range fields for multi-day assessments
            'date_start' => 'nullable|date',
            'date_end'   => 'nullable|date|after_or_equal:date_start',
            'department' => 'nullable|string|max:255',
            'company'    => 'nullable|string|max:255',
            // Process: higher-level work area that contains the job_task; syncs to ERA.
            'process'    => 'nullable|string|max:255',
            'job_task'   => 'nullable|string|max:255',
        ];

        foreach (self::REGIONS as $region) {
            $rules["{$region}_selected"]    = 'nullable|boolean';
            $rules["{$region}_due_to_work"] = 'nullable|boolean';
            $rules["{$region}_responses"]   = 'nullable|array';
            $rules["{$region}_responses.*.severity"]  = 'required_with:' . "{$region}_responses" . '|string|in:' . implode(',', self::SEVERITY_VALUES);
            $rules["{$region}_responses.*.frequency"] = 'required_with:' . "{$region}_responses" . '|string|in:' . implode(',', self::FREQUENCY_VALUES);
        }

        $validated = $request->validate($rules);

        $boss = BossQuestionnaire::create($validated);

        return response()->json(['id' => $boss->id, 'message' => 'BOSS Questionnaire saved successfully.'], 201);
    }

    public function show(int $id)
    {
        $boss = BossQuestionnaire::findOrFail($id);
        return response()->json($boss);
    }

    public function showByAssessment(int $assessmentId)
    {
        $assessment = EraAssessment::find($assessmentId);

        if (!$assessment || !$assessment->boss_questionnaire_id) {
            return response()->json(['linked' => false]);
        }

        $boss = BossQuestionnaire::find($assessment->boss_questionnaire_id);

        if (!$boss) {
            return response()->json(['linked' => false]);
        }

        $regions = [];
        foreach (self::REGIONS as $region) {
            $regions[$region] = [
                'selected'    => (bool) $boss->{"{$region}_selected"},
                'due_to_work' => $boss->{"{$region}_due_to_work"},
                'responses'   => $boss->{"{$region}_responses"} ?? [],
            ];
        }

        return response()->json([
            'linked'     => true,
            'boss_id'    => $boss->id,
            'name'       => $boss->name,
            'staff_id'   => $boss->staff_id,
            'date_start' => optional($boss->date_start)->toDateString(),
            'date_end'   => optional($boss->date_end)->toDateString(),
            'department' => $boss->department,
            'company'    => $boss->company,
            'process'    => $boss->process,
            'job_task'   => $boss->job_task,
            'regions'    => $regions,
        ]);
    }
}
