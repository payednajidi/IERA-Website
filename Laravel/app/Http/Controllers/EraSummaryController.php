<?php

namespace App\Http\Controllers;

use App\Models\EraAssessment;
use App\Models\EraSummaryPainPart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EraSummaryController extends Controller
{
    public function showPainParts(int $assessmentId)
    {
        if (!Schema::hasTable('era_summary_pain_parts')) {
            return response()->json([
                'assessment_id' => $assessmentId,
                'has_saved' => false,
                'pain_parts' => [],
                'warning' => 'era_summary_pain_parts table is missing. Run migrations.',
            ]);
        }

        $rows = EraSummaryPainPart::query()
            ->where('assessment_id', $assessmentId)
            ->orderBy('task_id')
            ->orderBy('body_part')
            ->get(['task_id', 'body_part']);

        $painParts = [];
        foreach ($rows as $row) {
            $taskKey = (string) $row->task_id;
            if (!isset($painParts[$taskKey])) {
                $painParts[$taskKey] = [];
            }
            $painParts[$taskKey][] = $row->body_part;
        }

        return response()->json([
            'assessment_id' => $assessmentId,
            'has_saved' => $rows->isNotEmpty(),
            'pain_parts' => $painParts,
        ]);
    }

    public function savePainParts(Request $request)
    {
        if (!Schema::hasTable('era_summary_pain_parts')) {
            return response()->json([
                'message' => 'Summary pain-part table is missing. Please run database migrations.',
            ], 503);
        }

        $validated = $request->validate([
            'assessment_id' => 'required|exists:era_assessments,id',
            'pain_parts' => 'required|array',
            'pain_parts.*.task_id' => 'required|integer|exists:era_tasks,id',
            'pain_parts.*.body_part' => 'required|string|max:100',
        ]);

        $assessment = EraAssessment::with('processes.tasks:id,process_id')->findOrFail((int) $validated['assessment_id']);
        $taskIds = $assessment->processes
            ->flatMap(fn ($process) => $process->tasks->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $taskLookup = array_flip($taskIds);

        $normalized = collect($validated['pain_parts'])->map(function ($row) {
            return [
                'task_id' => (int) $row['task_id'],
                'body_part' => $this->normalizeBodyPart((string) $row['body_part']),
            ];
        })->filter(fn ($row) => $row['body_part'] !== '')
          ->unique(fn ($row) => $row['task_id'] . '|' . $row['body_part'])
          ->values();

        foreach ($normalized as $row) {
            if (!isset($taskLookup[$row['task_id']])) {
                return response()->json([
                    'message' => 'One or more task IDs are invalid for this assessment.',
                ], 422);
            }
        }

        DB::transaction(function () use ($assessment, $normalized) {
            EraSummaryPainPart::where('assessment_id', $assessment->id)->delete();

            if ($normalized->isNotEmpty()) {
                $now = now();
                EraSummaryPainPart::insert($normalized->map(function ($row) use ($assessment, $now) {
                    return [
                        'assessment_id' => $assessment->id,
                        'task_id' => $row['task_id'],
                        'body_part' => $row['body_part'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all());
            }
        });

        $savedRows = EraSummaryPainPart::query()
            ->where('assessment_id', $assessment->id)
            ->orderBy('task_id')
            ->orderBy('body_part')
            ->get(['task_id', 'body_part']);

        $painParts = [];
        foreach ($savedRows as $row) {
            $taskKey = (string) $row->task_id;
            if (!isset($painParts[$taskKey])) {
                $painParts[$taskKey] = [];
            }
            $painParts[$taskKey][] = $row->body_part;
        }

        return response()->json([
            'message' => 'Summary pain parts saved successfully.',
            'assessment_id' => $assessment->id,
            'has_saved' => $savedRows->isNotEmpty(),
            'pain_parts' => $painParts,
        ]);
    }

    private function normalizeBodyPart(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(['-', '_'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return $value ?? '';
    }
}
