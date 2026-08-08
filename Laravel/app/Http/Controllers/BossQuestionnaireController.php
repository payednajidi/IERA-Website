<?php

namespace App\Http\Controllers;

use App\Models\BossGroup;
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
            'name'          => 'required|string|max:255',
            'staff_id'      => 'nullable|string|max:100',
            // Date range fields for multi-day assessments
            'date_start'    => 'nullable|date',
            'date_end'      => 'nullable|date|after_or_equal:date_start',
            'department'    => 'nullable|string|max:255',
            'company'       => 'nullable|string|max:255',
            // Process: higher-level work area that contains the job_task; syncs to ERA.
            'process'       => 'nullable|string|max:255',
            'job_task'      => 'nullable|string|max:255',
            // Optional: pass an existing group ID to link this record to the same session group.
            // We only validate the type here; existence is checked in code so a stale client-side
            // ID never causes a 422 — we simply create a fresh group instead.
            'boss_group_id' => 'nullable|integer',
        ];

        foreach (self::REGIONS as $region) {
            $rules["{$region}_selected"]    = 'nullable|boolean';
            $rules["{$region}_due_to_work"] = 'nullable|boolean';
            $rules["{$region}_responses"]   = 'nullable|array';
            $rules["{$region}_responses.*.severity"]  = 'required_with:' . "{$region}_responses" . '|string|in:' . implode(',', self::SEVERITY_VALUES);
            $rules["{$region}_responses.*.frequency"] = 'required_with:' . "{$region}_responses" . '|string|in:' . implode(',', self::FREQUENCY_VALUES);
        }

        $validated = $request->validate($rules);

        // Resolve or create the BOSS group for this submission.
        // If the client provides a valid group ID, reuse it; otherwise (ID missing, zero, or
        // referring to a deleted group) create a new group container so submission never fails
        // due to a stale localStorage value.
        $requestedGroupId = !empty($validated['boss_group_id']) ? (int) $validated['boss_group_id'] : null;

        if ($requestedGroupId && BossGroup::where('id', $requestedGroupId)->exists()) {
            $groupId = $requestedGroupId;
        } else {
            $group   = BossGroup::create([]);
            $groupId = $group->id;
        }

        $validated['boss_group_id'] = $groupId;

        $boss = BossQuestionnaire::create($validated);

        return response()->json([
            'id'            => $boss->id,
            'boss_group_id' => $groupId,
            'message'       => 'BOSS Questionnaire saved successfully.',
        ], 201);
    }

    public function show(int $id)
    {
        $boss = BossQuestionnaire::findOrFail($id);
        return response()->json($boss);
    }

    public function showByAssessment(int $assessmentId, \Illuminate\Http\Request $request)
    {
        $assessment = EraAssessment::find($assessmentId);

        if (!$assessment) {
            return response()->json(['boss_group_id' => null, 'boss_id' => null, 'selected_body_parts' => []]);
        }

        // Resolve the BOSS group ID from three sources (priority order):
        // 1. FK stored on the ERA assessment row (set when the ERA was submitted normally)
        // 2. ?boss_group_id query parameter (sent by the client when localStorage has the group ID
        //    but the ERA row was created before the group was linked — e.g. after a page reset)
        // 3. Nothing → fall back to legacy single-questionnaire FK
        $groupId = $assessment->boss_group_id
            ?? ($request->query('boss_group_id') ? (int) $request->query('boss_group_id') : null);

        if ($groupId) {
            $group = BossGroup::with('questionnaires')->find($groupId);

            if ($group) {
                // Opportunistically persist the link so future calls skip the query param.
                if (!$assessment->boss_group_id) {
                    $assessment->boss_group_id = $group->id;
                    $assessment->save();
                }

                // ── Per-task body-part mapping ────────────────────────────────────
                // Load ERA processes+tasks so we can match each task to the BOSS record
                // that was filled in for the same process / job_task combination.
                $assessment->load(['processes.tasks']);

                $taskBodyParts = [];          // task_id (string) → string[]
                $noMatchTaskIds = [];         // tasks with no corresponding BOSS record

                foreach ($assessment->processes as $eraProcess) {
                    $eraProcessNorm = mb_strtolower(trim($eraProcess->name ?? ''));

                    foreach ($eraProcess->tasks as $eraTask) {
                        $eraTaskNorm = mb_strtolower(trim($eraTask->title ?? ''));
                        $taskKey     = (string) $eraTask->id;
                        $matched     = null;  // null = no match yet; [] = matched, no pain

                        foreach ($group->questionnaires as $q) {
                            $qProcess = mb_strtolower(trim($q->process   ?? ''));
                            $qTask    = mb_strtolower(trim($q->job_task  ?? ''));

                            if ($qProcess === $eraProcessNorm && $qTask === $eraTaskNorm) {
                                $matched = array_merge($matched ?? [], $q->getSelectedBodyParts());
                            }
                        }

                        if ($matched !== null) {
                            // Matched: store the de-duplicated body parts for this task.
                            // An empty array here is intentional — it means the worker
                            // completed the BOSS for this task and reported no affected regions.
                            $taskBodyParts[$taskKey] = array_values(array_unique($matched));
                        } else {
                            // No BOSS record matches this ERA task (e.g. user renamed it).
                            // Flag it so the frontend can fall back to the flat union.
                            $noMatchTaskIds[] = $taskKey;
                        }
                    }
                }

                return response()->json([
                    'boss_group_id'       => $group->id,
                    'boss_id'             => null,
                    'selected_body_parts' => $group->getAllSelectedBodyParts(), // flat union (fallback)
                    'task_body_parts'     => $taskBodyParts,    // per-task authoritative map
                    'no_match_task_ids'   => $noMatchTaskIds,   // tasks without a BOSS match
                ]);
            }
        }

        // Legacy fallback: single BOSS questionnaire linked directly to the assessment.
        if ($assessment->boss_questionnaire_id) {
            $boss = BossQuestionnaire::find($assessment->boss_questionnaire_id);
            if ($boss) {
                return response()->json([
                    'boss_group_id'       => null,
                    'boss_id'             => $boss->id,
                    'selected_body_parts' => $boss->getSelectedBodyParts(),
                    'task_body_parts'     => [],
                    'no_match_task_ids'   => [],
                ]);
            }
        }

        return response()->json([
            'boss_group_id'     => null,
            'boss_id'           => null,
            'selected_body_parts' => [],
            'task_body_parts'   => [],
            'no_match_task_ids' => [],
        ]);
    }
}
