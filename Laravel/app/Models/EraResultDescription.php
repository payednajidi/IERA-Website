<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EraResultDescription extends Model
{
    protected $table = 'era_result_descriptions';

    protected $fillable = ['assessment_id', 'task_id', 'risk_factor_key', 'bullets'];

    protected $casts = ['bullets' => 'array'];
}
