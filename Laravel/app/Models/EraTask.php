<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EraTask extends Model
{
    protected $fillable = [
        'process_id',
        'title',
        'description',
        'worker_activities',
        'row_number',
    ];

    public function process()
    {
        return $this->belongsTo(EraProcess::class, 'process_id');
    }

    public function checklistAnswers()
    {
        return $this->hasMany(EraChecklistAnswer::class, 'task_id');
    }

}
