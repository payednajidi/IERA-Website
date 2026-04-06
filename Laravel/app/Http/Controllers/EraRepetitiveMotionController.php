<?php

namespace App\Http\Controllers;

use App\Models\EraAssessment;
use App\Models\EraRepetitiveMotionResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EraRepetitiveMotionController extends Controller
{
    public function show($assessmentId)
    {
        if ((int) $assessmentId <= 0) {
            $taskIds = [];

            return response()->json([
                'assessment_id' => null,
                'tasks' => [],
                'rows' => $this->defaultRows($taskIds),
                'task_not_applicable' => $this->defaultTaskNotApplicable($taskIds),
            ]);
        }

        $assessment = EraAssessment::with(['processes.tasks', 'checklistAnswers', 'forcefulExertionRows'])
            ->findOrFail($assessmentId);

        if (!$assessment->checklistAnswers()->exists()) {
            return response()->json([
                'message' => 'Please complete and save ERA checklist first.',
            ], 422);
        }

        if (!$assessment->forcefulExertionRows()->exists()) {
            return response()->json([
                'message' => 'Please complete and save Forceful Exertion (Step 3) first.',
            ], 422);
        }

        $taskIds = $this->taskIdsForAssessment($assessment);
        $responses = EraRepetitiveMotionResponse::where('assessment_id', $assessment->id)
            ->whereIn('task_id', $taskIds)
            ->get();

        return response()->json([
            'assessment_id' => $assessment->id,
            'tasks' => $assessment->processes
                ->flatMap(fn ($process) => $process->tasks)
                ->values()
                ->map(fn ($task) => [
                    'id' => (int) $task->id,
                    'title' => $task->title,
                ])
                ->all(),
            'rows' => $responses->isEmpty()
                ? $this->defaultRows($taskIds)
                : $this->buildRowsFromSaved($taskIds, $responses),
            'task_not_applicable' => $responses->isEmpty()
                ? $this->defaultTaskNotApplicable($taskIds)
                : $this->buildTaskNotApplicable($taskIds, $responses),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'assessment_id' => 'required|exists:era_assessments,id',
            'task_not_applicable' => 'required|array',
            'rows' => 'required|array|min:1',
            'rows.*.key' => 'required|string',
            'rows.*.remarks' => 'nullable|string',
            'rows.*.responses' => 'required|array|min:1',
            'rows.*.responses.*.task_id' => 'required|exists:era_tasks,id',
            'rows.*.responses.*.answer' => 'required|boolean',
            'rows.*.responses.*.not_applicable' => 'required|boolean',
        ]);

        $assessment = EraAssessment::with(['processes.tasks'])->findOrFail($validated['assessment_id']);
        $taskIds = $this->taskIdsForAssessment($assessment);
        $taskLookup = array_flip($taskIds);
        $allowedRowKeys = array_flip(array_map(
            fn ($row) => $row['key'],
            $this->rowDefinitions()
        ));

        foreach ($validated['rows'] as $row) {
            if (!isset($allowedRowKeys[$row['key']])) {
                return response()->json([
                    'message' => 'One or more repetitive-motion row keys are invalid.',
                ], 422);
            }

            foreach ($row['responses'] as $response) {
                if (!isset($taskLookup[(int) $response['task_id']])) {
                    return response()->json([
                        'message' => 'One or more repetitive-motion task IDs are invalid for this assessment.',
                    ], 422);
                }
            }
        }

        DB::transaction(function () use ($validated, $assessment) {
            EraRepetitiveMotionResponse::where('assessment_id', $assessment->id)->delete();

            $now = now();
            $insertRows = [];

            foreach ($validated['rows'] as $row) {
                foreach ($row['responses'] as $response) {
                    $taskId = (int) $response['task_id'];
                    $taskNA = (bool) ($validated['task_not_applicable'][(string) $taskId] ?? false);
                    $notApplicable = $taskNA || (bool) $response['not_applicable'];

                    $insertRows[] = [
                        'assessment_id' => $assessment->id,
                        'task_id' => $taskId,
                        'row_key' => $row['key'],
                        'answer' => $notApplicable ? false : (bool) $response['answer'],
                        'not_applicable' => $notApplicable,
                        'remarks' => trim((string) ($row['remarks'] ?? '')) !== '' ? trim((string) $row['remarks']) : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (!empty($insertRows)) {
                EraRepetitiveMotionResponse::insert($insertRows);
            }
        });

        return response()->json([
            'message' => 'Repetitive motion checklist saved successfully.',
        ]);
    }

    private function taskIdsForAssessment(EraAssessment $assessment): array
    {
        return $assessment->processes
            ->flatMap(fn ($process) => $process->tasks->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function rowDefinitions(): array
    {
        return [
            [
                'key' => 'neck_shoulders_elbows_wrists_hands_knee_seq_more_than_twice_per_minute',
                'body_part' => 'Neck, shoulders, elbows, wrists, hands, knee',
                'physical_risk_factor' => 'Work involving repetitive sequence of movement more than twice per minute',
                'max_exposure_duration' => 'More than 3 hours on a normal workday',
            ],
            [
                'key' => 'neck_shoulders_elbows_wrists_hands_knee_intensive_data_entry',
                'body_part' => 'Neck, shoulders, elbows, wrists, hands, knee',
                'physical_risk_factor' => 'Work involving intensive use of fingers, hands or wrist or work involving intensive data entry (key-in)',
                'max_exposure_duration' => 'More than 3 hours on a normal workday',
            ],
            [
                'key' => 'neck_shoulders_elbows_wrists_hands_knee_continuous_shoulder_arm',
                'body_part' => 'Neck, shoulders, elbows, wrists, hands, knee',
                'physical_risk_factor' => 'Work involving repetitive shoulder/arm movement with some pauses OR continuous shoulder/arm movement',
                'max_exposure_duration' => 'More than 1 hour continuously without a break',
            ],
            [
                'key' => 'neck_shoulders_elbows_wrists_hands_knee_heel_base_of_palm_hammer',
                'body_part' => 'Neck, shoulders, elbows, wrists, hands, knee',
                'physical_risk_factor' => 'Work using the heel/base of palm as a hammer more than once per minute',
                'max_exposure_duration' => 'More than 2 hours per day',
            ],
            [
                'key' => 'neck_shoulders_elbows_wrists_hands_knee_knee_as_hammer',
                'body_part' => 'Neck, shoulders, elbows, wrists, hands, knee',
                'physical_risk_factor' => 'Work using the knee as a hammer more than once per minute',
                'max_exposure_duration' => 'More than 2 hours per day',
            ],
        ];
    }

    private function defaultRows(array $taskIds): array
    {
        return collect($this->rowDefinitions())->map(function ($row) use ($taskIds) {
            return [
                ...$row,
                'remarks' => '',
                'responses' => collect($taskIds)->map(fn ($taskId) => [
                    'task_id' => (int) $taskId,
                    'answer' => false,
                    'not_applicable' => false,
                ])->values()->all(),
            ];
        })->values()->all();
    }

    private function buildRowsFromSaved(array $taskIds, Collection $saved): array
    {
        return collect($this->rowDefinitions())->map(function ($row) use ($taskIds, $saved) {
            $savedForKey = $saved->where('row_key', $row['key']);
            $remarks = $savedForKey->isNotEmpty() ? (string) ($savedForKey->first()->remarks ?? '') : '';

            return [
                ...$row,
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
    }

    private function defaultTaskNotApplicable(array $taskIds): array
    {
        return collect($taskIds)->mapWithKeys(fn ($taskId) => [
            (string) $taskId => false,
        ])->all();
    }

    private function buildTaskNotApplicable(array $taskIds, Collection $saved): array
    {
        $out = [];
        foreach ($taskIds as $taskId) {
            $rowsForTask = $saved->where('task_id', (int) $taskId);
            $out[(string) $taskId] = $rowsForTask->isNotEmpty()
                ? $rowsForTask->every(fn ($row) => (bool) $row->not_applicable)
                : false;
        }

        return $out;
    }
}
