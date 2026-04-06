<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EraChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'checklist_template_id',
        'body_part',
        'description',
        'max_duration',
        'order',
    ];

    public function template()
    {
        return $this->belongsTo(EraChecklistTemplate::class, 'checklist_template_id');
    }

    public function answers()
    {
        return $this->hasMany(EraChecklistAnswer::class, 'checklist_item_id');
    }
}
