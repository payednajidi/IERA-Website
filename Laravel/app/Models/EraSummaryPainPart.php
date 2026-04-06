<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EraSummaryPainPart extends Model
{
    protected $fillable = [
        'assessment_id',
        'task_id',
        'body_part',
    ];
}

