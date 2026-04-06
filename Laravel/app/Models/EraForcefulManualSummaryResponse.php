<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EraForcefulManualSummaryResponse extends Model
{
    protected $fillable = [
        'assessment_id',
        'task_id',
        'row_key',
        'answer',
        'not_applicable',
        'remarks',
    ];

    protected $casts = [
        'answer' => 'boolean',
        'not_applicable' => 'boolean',
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
