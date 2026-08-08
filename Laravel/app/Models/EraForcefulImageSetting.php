<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EraForcefulImageSetting extends Model
{
    protected $fillable = [
        'assessment_id',
        'forceful_image_na',
        'seated_image_na',
    ];

    protected $casts = [
        'forceful_image_na' => 'boolean',
        'seated_image_na'   => 'boolean',
    ];
}
