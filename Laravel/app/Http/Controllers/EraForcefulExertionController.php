<?php

namespace App\Http\Controllers;

use App\Models\EraAssessment;
use App\Models\EraChecklistForcefulExertion;
use App\Models\EraForcefulCarryingActivityResponse;
use App\Models\EraForcefulManualSummaryResponse;
use App\Models\EraForcefulPushPullResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EraForcefulExertionController extends Controller
{
    public function show($assessmentId)
    {
        if ((int) $assessmentId <= 0) {
            $taskIds = [];

            return response()->json([
                'assessment_id' => null,
                'rows' => $this->defaultRows($taskIds),
                'reference_info' => $this->referenceInfoTable(),
                'push_pull' => $this->defaultPushPullPayload($taskIds),
                'carrying_summary' => $this->defaultCarryingSummaryPayload($taskIds),
                'manual_summary' => $this->defaultManualSummaryPayload($taskIds),
            ]);
        }

        $assessment = EraAssessment::findOrFail($assessmentId);
        if (!$assessment->checklistAnswers()->exists()) {
            return response()->json([
                'message' => 'Please complete and save the ERA checklist first.',
            ], 422);
        }

        $taskIds = $this->taskIdsForAssessment($assessment);

        $liftingRows = $this->loadLiftingRows($assessment->id, $taskIds);
        $pushPullResponses = EraForcefulPushPullResponse::where('assessment_id', $assessment->id)
            ->whereIn('task_id', $taskIds)
            ->get();
        $carryingResponses = EraForcefulCarryingActivityResponse::where('assessment_id', $assessment->id)
            ->whereIn('task_id', $taskIds)
            ->get();
        $manualSummaryResponses = EraForcefulManualSummaryResponse::where('assessment_id', $assessment->id)
            ->whereIn('task_id', $taskIds)
            ->get();

        return response()->json([
            'assessment_id' => $assessment->id,
            'rows' => $liftingRows,
            'reference_info' => $this->referenceInfoTable(),
            'push_pull' => $pushPullResponses->isEmpty()
                ? $this->defaultPushPullPayload($taskIds)
                : $this->buildPushPullPayloadFromSaved($taskIds, $pushPullResponses),
            'carrying_summary' => $carryingResponses->isEmpty()
                ? $this->defaultCarryingSummaryPayload($taskIds)
                : $this->buildCarryingSummaryPayloadFromSaved($taskIds, $carryingResponses),
            'manual_summary' => $manualSummaryResponses->isEmpty()
                ? $this->defaultManualSummaryPayload($taskIds)
                : $this->buildManualSummaryPayloadFromSaved($taskIds, $manualSummaryResponses),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'assessment_id' => 'required|exists:era_assessments,id',
            'rows' => 'required|array|min:1',
            'rows.*.key' => 'required|string',
            'rows.*.working_height' => 'required|string',
            'rows.*.recommended_weight' => 'nullable|string',
            'rows.*.current_weight' => 'nullable|string',
            'rows.*.remarks' => 'nullable|string',
            'rows.*.answers' => 'required|array|min:1',
            'rows.*.answers.*.task_id' => 'required|exists:era_tasks,id',
            'rows.*.answers.*.answer' => 'required|boolean',

            'push_pull' => 'required|array',
            'push_pull.responses' => 'required|array|min:1',
            'push_pull.responses.*.activity_key' => 'required|string|in:start_stop_load,keep_load_in_motion',
            'push_pull.responses.*.task_id' => 'required|exists:era_tasks,id',
            'push_pull.responses.*.answer' => 'required|boolean',
            'push_pull.responses.*.not_applicable' => 'required|boolean',

            'carrying_summary' => 'required|array',
            'carrying_summary.responses' => 'required|array|min:1',
            'carrying_summary.responses.*.row_key' => 'required|string',
            'carrying_summary.responses.*.task_id' => 'required|exists:era_tasks,id',
            'carrying_summary.responses.*.answer' => 'required|boolean',
            'carrying_summary.responses.*.not_applicable' => 'required|boolean',
            'carrying_summary.responses.*.remarks' => 'nullable|string',

            'manual_summary' => 'required|array',
            'manual_summary.responses' => 'required|array|min:1',
            'manual_summary.responses.*.row_key' => 'required|string',
            'manual_summary.responses.*.task_id' => 'required|exists:era_tasks,id',
            'manual_summary.responses.*.answer' => 'required|boolean',
            'manual_summary.responses.*.not_applicable' => 'required|boolean',
            'manual_summary.responses.*.remarks' => 'nullable|string',
        ]);

        $assessment = EraAssessment::findOrFail($validated['assessment_id']);
        $taskIds = $this->taskIdsForAssessment($assessment);
        $validTaskLookup = array_flip($taskIds);

        foreach ($validated['rows'] as $row) {
            foreach ($row['answers'] as $cell) {
                if (!isset($validTaskLookup[(int) $cell['task_id']])) {
                    return response()->json([
                        'message' => 'One or more lifting-row task IDs are invalid for this assessment.',
                    ], 422);
                }
            }
        }

        foreach ($validated['push_pull']['responses'] as $response) {
            if (!isset($validTaskLookup[(int) $response['task_id']])) {
                return response()->json([
                    'message' => 'One or more push/pull task IDs are invalid for this assessment.',
                ], 422);
            }
        }

        $allowedCarryingKeys = array_flip(array_map(
            fn ($row) => $row['key'],
            $this->carryingSummaryRowsDefinition()
        ));
        foreach ($validated['carrying_summary']['responses'] as $response) {
            if (!isset($validTaskLookup[(int) $response['task_id']])) {
                return response()->json([
                    'message' => 'One or more carrying-summary task IDs are invalid for this assessment.',
                ], 422);
            }
            if (!isset($allowedCarryingKeys[$response['row_key']])) {
                return response()->json([
                    'message' => 'One or more carrying-summary row keys are invalid.',
                ], 422);
            }
        }

        $allowedManualKeys = array_flip(array_map(
            fn ($row) => $row['key'],
            $this->manualSummaryRowsDefinition()
        ));
        foreach ($validated['manual_summary']['responses'] as $response) {
            if (!isset($validTaskLookup[(int) $response['task_id']])) {
                return response()->json([
                    'message' => 'One or more manual-summary task IDs are invalid for this assessment.',
                ], 422);
            }
            if (!isset($allowedManualKeys[$response['row_key']])) {
                return response()->json([
                    'message' => 'One or more manual-summary row keys are invalid.',
                ], 422);
            }
        }

        DB::transaction(function () use ($validated, $assessment) {
            $now = now();

            EraChecklistForcefulExertion::where('assessment_id', $assessment->id)->delete();
            $liftingInsertRows = [];
            foreach ($validated['rows'] as $row) {
                foreach ($row['answers'] as $answerCell) {
                    $liftingInsertRows[] = [
                        'assessment_id' => $assessment->id,
                        'task_id' => (int) $answerCell['task_id'],
                        'working_height_key' => $row['key'],
                        'working_height_label' => $row['working_height'],
                        'recommended_weight' => $row['recommended_weight'] ?? null,
                        'current_weight' => $row['current_weight'] ?? null,
                        'remarks' => $row['remarks'] ?? null,
                        'answer' => (bool) $answerCell['answer'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            if (!empty($liftingInsertRows)) {
                EraChecklistForcefulExertion::insert($liftingInsertRows);
            }

            EraForcefulPushPullResponse::where('assessment_id', $assessment->id)->delete();
            $pushPullInsertRows = [];
            foreach ($validated['push_pull']['responses'] as $row) {
                $pushPullInsertRows[] = [
                    'assessment_id' => $assessment->id,
                    'task_id' => (int) $row['task_id'],
                    'activity_key' => $row['activity_key'],
                    'answer' => (bool) $row['answer'],
                    'not_applicable' => (bool) $row['not_applicable'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if (!empty($pushPullInsertRows)) {
                EraForcefulPushPullResponse::insert($pushPullInsertRows);
            }

            EraForcefulCarryingActivityResponse::where('assessment_id', $assessment->id)->delete();
            $carryingInsertRows = [];
            foreach ($validated['carrying_summary']['responses'] as $row) {
                $carryingInsertRows[] = [
                    'assessment_id' => $assessment->id,
                    'task_id' => (int) $row['task_id'],
                    'row_key' => $row['row_key'],
                    'answer' => (bool) $row['answer'],
                    'not_applicable' => (bool) $row['not_applicable'],
                    'remarks' => trim((string) ($row['remarks'] ?? '')) !== '' ? trim((string) $row['remarks']) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if (!empty($carryingInsertRows)) {
                EraForcefulCarryingActivityResponse::insert($carryingInsertRows);
            }

            EraForcefulManualSummaryResponse::where('assessment_id', $assessment->id)->delete();
            $manualInsertRows = [];
            foreach ($validated['manual_summary']['responses'] as $row) {
                $manualInsertRows[] = [
                    'assessment_id' => $assessment->id,
                    'task_id' => (int) $row['task_id'],
                    'row_key' => $row['row_key'],
                    'answer' => (bool) $row['answer'],
                    'not_applicable' => (bool) $row['not_applicable'],
                    'remarks' => trim((string) ($row['remarks'] ?? '')) !== '' ? trim((string) $row['remarks']) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if (!empty($manualInsertRows)) {
                EraForcefulManualSummaryResponse::insert($manualInsertRows);
            }
        });

        return response()->json([
            'message' => 'Forceful exertion checklist saved successfully.',
        ]);
    }

    private function taskIdsForAssessment(EraAssessment $assessment): array
    {
        return $assessment->processes()
            ->with('tasks:id,process_id')
            ->get()
            ->flatMap(fn ($process) => $process->tasks->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function loadLiftingRows(int $assessmentId, array $taskIds): array
    {
        $savedRows = EraChecklistForcefulExertion::where('assessment_id', $assessmentId)
            ->whereIn('task_id', $taskIds)
            ->orderBy('working_height_key')
            ->orderBy('task_id')
            ->get();

        if ($savedRows->isEmpty()) {
            return $this->defaultRows($taskIds);
        }

        $defaultTemplateMap = collect($this->defaultRows($taskIds))->keyBy('key');
        $grouped = $savedRows->groupBy('working_height_key');
        $formatted = [];

        foreach ($defaultTemplateMap as $heightKey => $templateRow) {
            $savedGroup = $grouped->get($heightKey, collect());
            if ($savedGroup->isEmpty()) {
                $formatted[] = $templateRow;
                continue;
            }

            $first = $savedGroup->first();
            $answers = collect($taskIds)->map(function ($taskId) use ($savedGroup) {
                $saved = $savedGroup->firstWhere('task_id', (int) $taskId);
                return [
                    'task_id' => (int) $taskId,
                    'answer' => $saved ? (bool) $saved->answer : false,
                ];
            })->values()->all();

            $formatted[] = [
                'key' => $heightKey,
                'working_height' => $first->working_height_label,
                'recommended_weight' => $first->recommended_weight ?? '',
                'current_weight' => $first->current_weight ?? '',
                'remarks' => $first->remarks ?? '',
                'answers' => $answers,
            ];
        }

        return $formatted;
    }

    private function defaultRows(array $taskIds): array
    {
        return [
            [
                'key' => 'between_floor_and_mid_lower_leg',
                'working_height' => 'Between floor to mid-lower leg',
                'recommended_weight' => '',
                'current_weight' => '',
                'remarks' => '',
                'answers' => $this->defaultAnswersByTask($taskIds),
            ],
            [
                'key' => 'between_mid_lower_leg_and_knuckle',
                'working_height' => 'Between mid-lower leg to knuckle',
                'recommended_weight' => '',
                'current_weight' => '',
                'remarks' => '',
                'answers' => $this->defaultAnswersByTask($taskIds),
            ],
            [
                'key' => 'between_knuckle_and_elbow',
                'working_height' => 'Between knuckle height and elbow',
                'recommended_weight' => '',
                'current_weight' => '',
                'remarks' => '',
                'answers' => $this->defaultAnswersByTask($taskIds),
            ],
            [
                'key' => 'between_elbow_and_shoulder',
                'working_height' => 'Between elbow and shoulder',
                'recommended_weight' => '',
                'current_weight' => '',
                'remarks' => '',
                'answers' => $this->defaultAnswersByTask($taskIds),
            ],
            [
                'key' => 'above_shoulder',
                'working_height' => 'Above the shoulder',
                'recommended_weight' => '',
                'current_weight' => '',
                'remarks' => '',
                'answers' => $this->defaultAnswersByTask($taskIds),
            ],
        ];
    }

    private function defaultAnswersByTask(array $taskIds): array
    {
        return collect($taskIds)->map(fn ($taskId) => [
            'task_id' => (int) $taskId,
            'answer' => false,
        ])->values()->all();
    }

    private function referenceInfoTable(): array
    {
        return [
            'repetitive_handling' => [
                ['if_employee_repeats' => 'Once or twice per minutes', 'weight_reduction' => '30%'],
                ['if_employee_repeats' => 'Five to eight times per minute', 'weight_reduction' => '50%'],
                ['if_employee_repeats' => 'More than 12 times per minute', 'weight_reduction' => '80%'],
            ],
            'twisted_body_posture' => [
                ['twist_angle' => '45 degrees', 'weight_reduction' => '10%'],
                ['twist_angle' => '90 degrees', 'weight_reduction' => '20%'],
            ],
        ];
    }

    private function defaultPushPullPayload(array $taskIds): array
    {
        $activities = $this->pushPullActivitiesDefinition();

        $formattedActivities = collect($activities)->map(function ($activity) use ($taskIds) {
            return [
                'key' => $activity['key'],
                'activity' => $activity['activity'],
                'male_recommended' => $activity['male_recommended'],
                'female_recommended' => $activity['female_recommended'],
                'responses' => collect($taskIds)->map(fn ($taskId) => [
                    'task_id' => (int) $taskId,
                    'answer' => false,
                    'not_applicable' => false,
                ])->values()->all(),
            ];
        })->values()->all();

        return [
            'conditions' => $this->pushPullConditions(),
            'activities' => $formattedActivities,
            'task_not_applicable' => $this->defaultTaskNotApplicableMap($taskIds),
        ];
    }

    private function buildPushPullPayloadFromSaved(array $taskIds, Collection $saved): array
    {
        $activities = $this->pushPullActivitiesDefinition();

        $taskNotApplicable = [];
        foreach ($taskIds as $taskId) {
            $rowsForTask = $saved->where('task_id', (int) $taskId);
            $taskNotApplicable[(string) $taskId] = $rowsForTask->isNotEmpty()
                ? $rowsForTask->every(fn ($row) => (bool) $row->not_applicable)
                : false;
        }

        $formattedActivities = collect($activities)->map(function ($activity) use ($taskIds, $saved) {
            return [
                'key' => $activity['key'],
                'activity' => $activity['activity'],
                'male_recommended' => $activity['male_recommended'],
                'female_recommended' => $activity['female_recommended'],
                'responses' => collect($taskIds)->map(function ($taskId) use ($activity, $saved) {
                    $found = $saved->first(function ($row) use ($activity, $taskId) {
                        return $row->activity_key === $activity['key'] && (int) $row->task_id === (int) $taskId;
                    });

                    return [
                        'task_id' => (int) $taskId,
                        'answer' => $found ? (bool) $found->answer : false,
                        'not_applicable' => $found ? (bool) $found->not_applicable : false,
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return [
            'conditions' => $this->pushPullConditions(),
            'activities' => $formattedActivities,
            'task_not_applicable' => $taskNotApplicable,
        ];
    }

    private function pushPullActivitiesDefinition(): array
    {
        return [
            [
                'key' => 'start_stop_load',
                'activity' => 'Stopping or starting a load',
                'male_recommended' => 'Approximately 1000 kg load (equivalent to 200N pushing or pulling force) on smooth level surface using well maintained handling aid',
                'female_recommended' => 'Approximately 750 kg load (equivalent to 150N pushing or pulling force) on smooth level surface using well maintained handling aid',
            ],
            [
                'key' => 'keep_load_in_motion',
                'activity' => 'Keeping the load in motion',
                'male_recommended' => 'Approximately 100 kg load (equivalent to 100N pushing or pulling force) on uneven level surface using well maintained handling aid',
                'female_recommended' => 'Approximately 70 kg load (equivalent to 70N pushing or pulling force) on uneven level surface using well maintained handling aid',
            ],
        ];
    }

    private function pushPullConditions(): array
    {
        return [
            'Force is applied using hands',
            'Hands are between knuckle and shoulder height',
            'Distance for pushing or pulling is less than 20 meters',
            'Load is being supported on wheels',
            'Pushing or pulling uses a well maintained handling aid',
        ];
    }

    private function defaultCarryingSummaryPayload(array $taskIds): array
    {
        $rows = collect($this->carryingSummaryRowsDefinition())->map(function ($row) use ($taskIds) {
            return [
                'key' => $row['key'],
                'factor' => $row['factor'],
                'condition' => $row['condition'],
                'outcome' => $row['outcome'],
                'remarks' => '',
                'responses' => collect($taskIds)->map(fn ($taskId) => [
                    'task_id' => (int) $taskId,
                    'answer' => false,
                    'not_applicable' => false,
                ])->values()->all(),
            ];
        })->values()->all();

        return [
            'rows' => $rows,
            'task_not_applicable' => $this->defaultTaskNotApplicableMap($taskIds),
        ];
    }

    private function buildCarryingSummaryPayloadFromSaved(array $taskIds, Collection $saved): array
    {
        $definitions = $this->carryingSummaryRowsDefinition();
        $taskNotApplicable = [];
        foreach ($taskIds as $taskId) {
            $rowsForTask = $saved->where('task_id', (int) $taskId);
            $taskNotApplicable[(string) $taskId] = $rowsForTask->isNotEmpty()
                ? $rowsForTask->every(fn ($row) => (bool) $row->not_applicable)
                : false;
        }

        $rows = collect($definitions)->map(function ($def) use ($taskIds, $saved) {
            $savedForKey = $saved->where('row_key', $def['key']);
            $remarks = $savedForKey->isNotEmpty() ? (string) ($savedForKey->first()->remarks ?? '') : '';

            return [
                'key' => $def['key'],
                'factor' => $def['factor'],
                'condition' => $def['condition'],
                'outcome' => $def['outcome'],
                'remarks' => $remarks,
                'responses' => collect($taskIds)->map(function ($taskId) use ($savedForKey) {
                    $found = $savedForKey->firstWhere('task_id', (int) $taskId);
                    return [
                        'task_id' => (int) $taskId,
                        'answer' => $found ? (bool) $found->answer : false,
                        'not_applicable' => $found ? (bool) $found->not_applicable : false,
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return [
            'rows' => $rows,
            'task_not_applicable' => $taskNotApplicable,
        ];
    }

    private function carryingSummaryRowsDefinition(): array
    {
        return [
            [
                'key' => 'floor_surface_dry_clean',
                'factor' => 'Floor Surface',
                'condition' => 'Dry and clean floor in good condition',
                'outcome' => 'Acceptable',
            ],
            [
                'key' => 'floor_surface_poor_uneven',
                'factor' => 'Floor Surface',
                'condition' => 'Dry floor but in poor condition, worn or uneven',
                'outcome' => 'Conduct advanced ERA',
            ],
            [
                'key' => 'floor_surface_contaminated',
                'factor' => 'Floor Surface',
                'condition' => 'Contaminated/wet or steep sloping floor or unstable surface or unsuitable footwear',
                'outcome' => 'Conduct advanced ERA',
            ],
            [
                'key' => 'other_environmental_no_factor',
                'factor' => 'Other environmental factors',
                'condition' => 'No factors present',
                'outcome' => 'Acceptable',
            ],
            [
                'key' => 'other_environmental_has_factor',
                'factor' => 'Other environmental factors',
                'condition' => 'One or more factors present (e.g., poor lighting and strong air movements)',
                'outcome' => 'Conduct advanced ERA',
            ],
            [
                'key' => 'carry_distance_2_10',
                'factor' => 'Carry distance',
                'condition' => '2 m-10 m',
                'outcome' => 'Acceptable',
            ],
            [
                'key' => 'carry_distance_10_or_more',
                'factor' => 'Carry distance',
                'condition' => '10 m or more',
                'outcome' => 'Conduct advanced ERA',
            ],
            [
                'key' => 'obstacles_none',
                'factor' => 'Obstacles on route',
                'condition' => 'No obstacles and carry route is flat',
                'outcome' => 'Acceptable',
            ],
            [
                'key' => 'obstacles_present',
                'factor' => 'Obstacles on route',
                'condition' => 'Steep slope or up steps or through closed doors or trip hazards or using ladders',
                'outcome' => 'Conduct advanced ERA',
            ],
            [
                'key' => 'other_carrying',
                'factor' => 'Other',
                'condition' => '',
                'outcome' => '',
            ],
        ];
    }

    private function defaultManualSummaryPayload(array $taskIds): array
    {
        $rows = collect($this->manualSummaryRowsDefinition())->map(function ($row) use ($taskIds) {
            return [
                'key' => $row['key'],
                'activity' => $row['activity'],
                'recommended_weight' => $row['recommended_weight'],
                'remarks' => '',
                'responses' => collect($taskIds)->map(fn ($taskId) => [
                    'task_id' => (int) $taskId,
                    'answer' => false,
                    'not_applicable' => false,
                ])->values()->all(),
            ];
        })->values()->all();

        return [
            'rows' => $rows,
            'task_not_applicable' => $this->defaultTaskNotApplicableMap($taskIds),
        ];
    }

    private function buildManualSummaryPayloadFromSaved(array $taskIds, Collection $saved): array
    {
        $definitions = $this->manualSummaryRowsDefinition();
        $taskNotApplicable = [];
        foreach ($taskIds as $taskId) {
            $rowsForTask = $saved->where('task_id', (int) $taskId);
            $taskNotApplicable[(string) $taskId] = $rowsForTask->isNotEmpty()
                ? $rowsForTask->every(fn ($row) => (bool) $row->not_applicable)
                : false;
        }

        $rows = collect($definitions)->map(function ($def) use ($taskIds, $saved) {
            $savedForKey = $saved->where('row_key', $def['key']);
            $remarks = $savedForKey->isNotEmpty() ? (string) ($savedForKey->first()->remarks ?? '') : '';

            return [
                'key' => $def['key'],
                'activity' => $def['activity'],
                'recommended_weight' => $def['recommended_weight'],
                'remarks' => $remarks,
                'responses' => collect($taskIds)->map(function ($taskId) use ($savedForKey) {
                    $found = $savedForKey->firstWhere('task_id', (int) $taskId);
                    return [
                        'task_id' => (int) $taskId,
                        'answer' => $found ? (bool) $found->answer : false,
                        'not_applicable' => $found ? (bool) $found->not_applicable : false,
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return [
            'rows' => $rows,
            'task_not_applicable' => $taskNotApplicable,
        ];
    }

    private function manualSummaryRowsDefinition(): array
    {
        return [
            [
                'key' => 'lifting_lowering',
                'activity' => 'Lifting and lowering; or',
                'recommended_weight' => 'Figure 3.1 & Table 3.3',
            ],
            [
                'key' => 'repetitive_lifting_lowering',
                'activity' => 'Repetitive lifting and lowering; or',
                'recommended_weight' => 'Figure 3.1 & Table 3.4',
            ],
            [
                'key' => 'twisted_posture_lifting_lowering',
                'activity' => 'Twisted body posture while lifting and lowering; or',
                'recommended_weight' => 'Figure 3.1 & Table 3.5',
            ],
            [
                'key' => 'repetitive_with_twisted_posture',
                'activity' => 'Repetitive lifting and lowering with twisted body posture; or',
                'recommended_weight' => 'Based on Figure 3.1, Table 3.4 and Table 3.5',
            ],
            [
                'key' => 'pushing_pulling',
                'activity' => 'Pushing and pulling; or',
                'recommended_weight' => 'Based on Table 3.6',
            ],
            [
                'key' => 'handling_seated_position',
                'activity' => 'Handling in seated position; or',
                'recommended_weight' => 'Based on Figure 3.2',
            ],
            [
                'key' => 'carrying',
                'activity' => 'Carrying',
                'recommended_weight' => 'Based on Table 3.7',
            ],
            [
                'key' => 'other_forceful_activity',
                'activity' => 'Other Forceful Activity',
                'recommended_weight' => '',
            ],
        ];
    }

    private function defaultTaskNotApplicableMap(array $taskIds): array
    {
        return collect($taskIds)->mapWithKeys(fn ($taskId) => [
            (string) $taskId => false,
        ])->all();
    }
}
