<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BossQuestionnaire extends Model
{
    private const REGIONS = [
        'neck', 'shoulder', 'upper_back', 'lower_back',
        'upper_arm', 'elbow', 'lower_arm', 'hand_wrist',
        'thigh', 'knee', 'lower_leg', 'ankle_foot',
    ];

    private const REGION_LABEL_MAP = [
        'neck'       => 'neck',
        'shoulder'   => 'shoulder',
        'upper_back' => 'upper back',
        'lower_back' => 'lower back',
        'upper_arm'  => 'upper arm',
        'elbow'      => 'elbow',
        'lower_arm'  => 'lower arm',
        'hand_wrist' => 'hand/wrist',
        'thigh'      => 'thigh',
        'knee'       => 'knee',
        'lower_leg'  => 'lower leg',
        'ankle_foot' => 'ankle/foot',
    ];

    protected $fillable = [
        'boss_group_id',
        'name', 'staff_id', 'date_start', 'date_end', 'department', 'company', 'process', 'job_task',
        'neck_selected', 'neck_due_to_work', 'neck_responses',
        'shoulder_selected', 'shoulder_due_to_work', 'shoulder_responses',
        'upper_back_selected', 'upper_back_due_to_work', 'upper_back_responses',
        'lower_back_selected', 'lower_back_due_to_work', 'lower_back_responses',
        'upper_arm_selected', 'upper_arm_due_to_work', 'upper_arm_responses',
        'elbow_selected', 'elbow_due_to_work', 'elbow_responses',
        'lower_arm_selected', 'lower_arm_due_to_work', 'lower_arm_responses',
        'hand_wrist_selected', 'hand_wrist_due_to_work', 'hand_wrist_responses',
        'thigh_selected', 'thigh_due_to_work', 'thigh_responses',
        'knee_selected', 'knee_due_to_work', 'knee_responses',
        'lower_leg_selected', 'lower_leg_due_to_work', 'lower_leg_responses',
        'ankle_foot_selected', 'ankle_foot_due_to_work', 'ankle_foot_responses',
    ];

    protected $casts = [
        'date_start' => 'date',
        'date_end'   => 'date',
        'neck_selected' => 'boolean',
        'neck_due_to_work' => 'boolean',
        'neck_responses' => 'array',
        'shoulder_selected' => 'boolean',
        'shoulder_due_to_work' => 'boolean',
        'shoulder_responses' => 'array',
        'upper_back_selected' => 'boolean',
        'upper_back_due_to_work' => 'boolean',
        'upper_back_responses' => 'array',
        'lower_back_selected' => 'boolean',
        'lower_back_due_to_work' => 'boolean',
        'lower_back_responses' => 'array',
        'upper_arm_selected' => 'boolean',
        'upper_arm_due_to_work' => 'boolean',
        'upper_arm_responses' => 'array',
        'elbow_selected' => 'boolean',
        'elbow_due_to_work' => 'boolean',
        'elbow_responses' => 'array',
        'lower_arm_selected' => 'boolean',
        'lower_arm_due_to_work' => 'boolean',
        'lower_arm_responses' => 'array',
        'hand_wrist_selected' => 'boolean',
        'hand_wrist_due_to_work' => 'boolean',
        'hand_wrist_responses' => 'array',
        'thigh_selected' => 'boolean',
        'thigh_due_to_work' => 'boolean',
        'thigh_responses' => 'array',
        'knee_selected' => 'boolean',
        'knee_due_to_work' => 'boolean',
        'knee_responses' => 'array',
        'lower_leg_selected' => 'boolean',
        'lower_leg_due_to_work' => 'boolean',
        'lower_leg_responses' => 'array',
        'ankle_foot_selected' => 'boolean',
        'ankle_foot_due_to_work' => 'boolean',
        'ankle_foot_responses' => 'array',
    ];

    public function bossGroup()
    {
        return $this->belongsTo(BossGroup::class, 'boss_group_id');
    }

    public function eraAssessments()
    {
        return $this->hasMany(EraAssessment::class, 'boss_questionnaire_id');
    }

    /** Returns canonical body part names (matching BODY_PART_OPTIONS in EraSummary.vue) for all selected regions. */
    public function getSelectedBodyParts(): array
    {
        $selected = [];
        foreach (self::REGIONS as $region) {
            if ($this->{"${region}_selected"}) {
                $selected[] = self::REGION_LABEL_MAP[$region];
            }
        }
        return $selected;
    }
}
