<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EraProcess extends Model
{
    protected $fillable = [
        'assessment_id',
        'name',
    ];

    /**
     * A process belongs to one assessment
     */
    public function assessment()
    {
        return $this->belongsTo(EraAssessment::class, 'assessment_id');
    }

    /**
     * A process has many tasks
     */
    public function tasks()
    {
        return $this->hasMany(EraTask::class, 'process_id');
    }

}
