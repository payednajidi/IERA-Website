<?php

namespace App\Http\Controllers;

use App\Models\EraAssessment;
use App\Models\EraChecklistTemplate;
use App\Models\EraChecklistAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EraChecklistController extends Controller
{
    public function show($assessmentId)
    {
        $templates = EraChecklistTemplate::with('items')
            ->where('name', '!=', 'ERGONOMICS RISK FACTORS: FORCEFUL EXERTION')
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->unique('name')
            ->values();

        if ((int) $assessmentId <= 0) {
            return response()->json([
                'assessment' => null,
                'tasks' => [],
                'templates' => $templates,
                'answers' => [],
            ]);
        }

        $assessment = EraAssessment::with([
            'processes.tasks',
            'checklistAnswers'
        ])->findOrFail($assessmentId);

        // Collect all tasks from all processes
        $tasks = $assessment->processes->flatMap(function ($process) {
            return $process->tasks;
        })->values();

        return response()->json([
            'assessment' => $assessment,
            'tasks' => $tasks,
            'templates' => $templates,
            'answers' => $assessment->checklistAnswers,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'assessment_id' => 'required|exists:era_assessments,id',
            'answers' => 'required|array',
            'answers.*.task_id' => 'required|exists:era_tasks,id',
            'answers.*.checklist_item_id' => 'required|exists:era_checklist_items,id',
            'answers.*.answer' => 'required|boolean',
        ]);

        DB::transaction(function () use ($request) {

            EraChecklistAnswer::where('assessment_id', $request->assessment_id)->delete();

            foreach ($request->answers as $answer) {
                EraChecklistAnswer::create([
                    'assessment_id' => $request->assessment_id,
                    'task_id' => $answer['task_id'],
                    'checklist_item_id' => $answer['checklist_item_id'],
                    'answer' => $answer['answer'],
                ]);
            }
        });

        return response()->json([
            'message' => 'Checklist saved successfully.'
        ]);
    }
}
