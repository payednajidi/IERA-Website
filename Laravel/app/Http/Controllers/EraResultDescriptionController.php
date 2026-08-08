<?php

namespace App\Http\Controllers;

use App\Models\EraResultDescription;
use Illuminate\Http\Request;

class EraResultDescriptionController extends Controller
{
    public function index($assessmentId)
    {
        $rows = EraResultDescription::where('assessment_id', (int) $assessmentId)->get();
        return response()->json($rows);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'assessment_id'   => 'required|exists:era_assessments,id',
            'task_id'         => 'required|exists:era_tasks,id',
            'risk_factor_key' => 'required|string|max:64',
            'bullets'         => 'required|array',
            'bullets.*'       => 'string|max:1000',
        ]);

        $row = EraResultDescription::updateOrCreate(
            [
                'assessment_id'   => $validated['assessment_id'],
                'task_id'         => $validated['task_id'],
                'risk_factor_key' => $validated['risk_factor_key'],
            ],
            ['bullets' => $validated['bullets']]
        );

        return response()->json($row);
    }

    public function destroy($assessmentId, $taskId, $riskFactorKey)
    {
        EraResultDescription::where([
            'assessment_id'   => (int) $assessmentId,
            'task_id'         => (int) $taskId,
            'risk_factor_key' => $riskFactorKey,
        ])->delete();

        return response()->json(['message' => 'Restored to auto-generated description.']);
    }
}
