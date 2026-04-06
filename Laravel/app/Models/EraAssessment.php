<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EraAssessment extends Model
{
    protected $fillable = [
        'assessor_name',
        'assessment_date',
        'department',
        'working_hours',
        'breaks',
    ];

    /**
     * An assessment has many processes
     */
    public function processes()
    {
        return $this->hasMany(EraProcess::class, 'assessment_id');
    }

    /**
     * An assessment has many photo groups
     */
    public function photoGroups()
    {
        return $this->hasMany(EraPhotoGroup::class, 'assessment_id');
    }

    public function checklistAnswers()
    {
        return $this->hasMany(EraChecklistAnswer::class, 'assessment_id');
    }

    public function forcefulExertionRows()
    {
        return $this->hasMany(EraChecklistForcefulExertion::class, 'assessment_id');
    }

    public function forcefulPushPullResponses()
    {
        return $this->hasMany(EraForcefulPushPullResponse::class, 'assessment_id');
    }

    public function forcefulCarryingActivityResponses()
    {
        return $this->hasMany(EraForcefulCarryingActivityResponse::class, 'assessment_id');
    }

    public function forcefulManualSummaryResponses()
    {
        return $this->hasMany(EraForcefulManualSummaryResponse::class, 'assessment_id');
    }

    public function repetitiveMotionResponses()
    {
        return $this->hasMany(EraRepetitiveMotionResponse::class, 'assessment_id');
    }

    public function vibrationResponses()
    {
        return $this->hasMany(EraVibrationResponse::class, 'assessment_id');
    }

    public function environmentalFactorResponses()
    {
        return $this->hasMany(EraEnvironmentalFactorResponse::class, 'assessment_id');
    }

    public function summaryPainParts()
    {
        return $this->hasMany(EraSummaryPainPart::class, 'assessment_id');
    }

}
