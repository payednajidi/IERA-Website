<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BossGroup extends Model
{
    protected $fillable = [];

    /** All BOSS questionnaires that belong to this group. */
    public function questionnaires()
    {
        return $this->hasMany(BossQuestionnaire::class, 'boss_group_id');
    }

    /** All ERA assessments that reference this group. */
    public function eraAssessments()
    {
        return $this->hasMany(EraAssessment::class, 'boss_group_id');
    }

    /**
     * Aggregate selected body parts from every questionnaire in the group.
     * Returns a deduplicated array of canonical body-part labels.
     */
    public function getAllSelectedBodyParts(): array
    {
        return $this->questionnaires
            ->flatMap(fn ($q) => $q->getSelectedBodyParts())
            ->unique()
            ->values()
            ->all();
    }
}
