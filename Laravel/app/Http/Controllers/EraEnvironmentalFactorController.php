<?php

namespace App\Http\Controllers;

use App\Models\EraAssessment;
use App\Models\EraEnvironmentalFactorResponse;
use App\Models\EraVibrationResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EraEnvironmentalFactorController extends Controller
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

        if (!EraVibrationResponse::where('assessment_id', $assessment->id)->exists()) {
            return response()->json([
                'message' => 'Please complete and save Vibration (Step 5) first.',
            ], 422);
        }

        $taskIds = $this->taskIdsForAssessment($assessment);
        $responses = EraEnvironmentalFactorResponse::where('assessment_id', $assessment->id)
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
                    'message' => 'One or more environmental-factor row keys are invalid.',
                ], 422);
            }

            foreach ($row['responses'] as $response) {
                if (!isset($taskLookup[(int) $response['task_id']])) {
                    return response()->json([
                        'message' => 'One or more environmental-factor task IDs are invalid for this assessment.',
                    ], 422);
                }
            }
        }

        DB::transaction(function () use ($validated, $assessment) {
            EraEnvironmentalFactorResponse::where('assessment_id', $assessment->id)->delete();

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
                EraEnvironmentalFactorResponse::insert($insertRows);
            }
        });

        return response()->json([
            'message' => 'Environmental factors checklist saved successfully.',
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
                'key' => 'inadequate_lighting',
                'physical_risk_factor' => 'Inadequate lighting',
            ],
            [
                'key' => 'extreme_temperature',
                'physical_risk_factor' => 'Extreme temperature (hot/cold)',
            ],
            [
                'key' => 'inadequate_air_ventilation',
                'physical_risk_factor' => 'Inadequate air ventilation or poor IAQ',
            ],
            [
                'key' => 'noise_above_pel',
                'physical_risk_factor' => 'Noise exposure above PEL',
            ],
            [
                'key' => 'annoying_noise_more_than_8_hours',
                'physical_risk_factor' => 'Exposed to annoying noise more than 8 hours',
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
