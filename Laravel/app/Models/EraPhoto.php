<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EraPhoto extends Model
{
    protected $fillable = [
        'photo_group_id',
        'file_path',
    ];

    public function photoGroup()
    {
        return $this->belongsTo(EraPhotoGroup::class, 'photo_group_id');
    }
}
