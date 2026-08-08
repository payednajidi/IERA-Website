<?php

namespace App\Http\Controllers;

use App\Models\BossGroup;

class BossGroupController extends Controller
{
    /**
     * Return a group with all its questionnaires and aggregated body parts.
     */
    public function show(int $id)
    {
        $group = BossGroup::with('questionnaires')->findOrFail($id);

        // Attach per-questionnaire selected_body_parts so the frontend can
        // display which regions each worker reported without a second round-trip.
        $questionnaires = $group->questionnaires->map(fn ($q) => array_merge(
            $q->toArray(),
            ['selected_body_parts' => $q->getSelectedBodyParts()]
        ));

        return response()->json([
            'id'                  => $group->id,
            'questionnaires'      => $questionnaires,
            'selected_body_parts' => $group->getAllSelectedBodyParts(),
        ]);
    }
}
