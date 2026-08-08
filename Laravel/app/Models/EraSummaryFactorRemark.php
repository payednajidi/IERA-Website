<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EraSummaryFactorRemark extends Model
{
    protected $fillable = [
        'assessment_id',
        'factor_key',
        'remarks',
    ];

    public function assessment()
    {
        return $this->belongsTo(EraAssessment::class, 'assessment_id');
    }
}
