<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EraPhotoGroup extends Model
{
    protected $fillable = [
        'assessment_id',
        'task_id', // ✅ allow task_id to be saved
        'title',
        'description',
    ];

    public function assessment()
    {
        return $this->belongsTo(EraAssessment::class, 'assessment_id');
    }

    public function task()
    {
        return $this->belongsTo(EraTask::class, 'task_id');
    }

    public function photos()
    {
        return $this->hasMany(EraPhoto::class, 'photo_group_id');
    }
}
