<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EraChecklistAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_id',
        'task_id',
        'checklist_item_id',
        'answer',
    ];

    public function assessment()
    {
        return $this->belongsTo(EraAssessment::class);
    }

    public function task()
    {
        return $this->belongsTo(EraTask::class);
    }

    public function checklistItem()
    {
        return $this->belongsTo(EraChecklistItem::class);
    }
}
