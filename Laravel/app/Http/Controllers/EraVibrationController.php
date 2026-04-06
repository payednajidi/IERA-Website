<?php

namespace App\Http\Controllers;

use App\Models\EraAssessment;
use App\Models\EraRepetitiveMotionResponse;
use App\Models\EraVibrationResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EraVibrationController extends Controller
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

        $assessment = EraAssessment::with(['processes.tasks', 'checklistAnswers'])
            ->findOrFail($assessmentId);

        if (!$assessment->checklistAnswers()->exists()) {
            return response()->json([
                'message' => 'Please complete and save ERA checklist first.',
            ], 422);
        }

        if (!EraRepetitiveMotionResponse::where('assessment_id', $assessment->id)->exists()) {
            return response()->json([
                'message' => 'Please complete and save Repetitive Motion (Step 4) first.',
            ], 422);
        }

        $taskIds = $this->taskIdsForAssessment($assessment);
        $responses = EraVibrationResponse::where('assessment_id', $assessment->id)
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
                    'message' => 'One or more vibration row keys are invalid.',
                ], 422);
            }

            foreach ($row['responses'] as $response) {
                if (!isset($taskLookup[(int) $response['task_id']])) {
                    return response()->json([
                        'message' => 'One or more vibration task IDs are invalid for this assessment.',
                    ], 422);
                }
            }
        }

        DB::transaction(function () use ($validated, $assessment) {
            EraVibrationResponse::where('assessment_id', $assessment->id)->delete();

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
                EraVibrationResponse::insert($insertRows);
            }
        });

        return response()->json([
            'message' => 'Vibration checklist saved successfully.',
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
                'key' => 'hand_arm_without_ppe',
                'body_part' => 'Hand-Arm (segmental vibration)',
                'physical_risk_factor' => 'Work using power tools (e.g. battery powered/electrical pneumatic/hydraulic) without PPE',
                'max_exposure_duration' => 'More than 50 minutes in an hour',
            ],
            [
                'key' => 'hand_arm_with_ppe',
                'body_part' => 'Hand-Arm (segmental vibration)',
                'physical_risk_factor' => 'Work using power tools (e.g. battery powered/electrical pneumatic/hydraulic) with PPE',
                'max_exposure_duration' => 'More than 5 hours in 8 hours shift work',
            ],
            [
                'key' => 'whole_body_exposure',
                'body_part' => 'Whole body',
                'physical_risk_factor' => 'Work involving exposure to whole body vibration',
                'max_exposure_duration' => 'More than 5 hours in 8 hours shift work',
            ],
            [
                'key' => 'whole_body_with_complaint',
                'body_part' => 'Whole body',
                'physical_risk_factor' => 'Work involving exposure to whole body vibration combined employee complaint of excessive body shaking',
                'max_exposure_duration' => 'More than 3 hours in 8 hours shift work',
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
