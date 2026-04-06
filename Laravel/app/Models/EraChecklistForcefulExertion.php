<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EraChecklistForcefulExertion extends Model
{
    protected $table = 'era_checklist_forceful_exertions';

    protected $fillable = [
        'assessment_id',
        'task_id',
        'working_height_key',
        'working_height_label',
        'recommended_weight',
        'current_weight',
        'remarks',
        'answer',
    ];

    protected $casts = [
        'answer' => 'boolean',
    ];

    public function assessment()
    {
        return $this->belongsTo(EraAssessment::class, 'assessment_id');
    }

    public function task()
    {
        return $this->belongsTo(EraTask::class, 'task_id');
    }
}
