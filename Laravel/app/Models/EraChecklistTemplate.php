<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EraChecklistTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'order',
    ];

    public function items()
    {
        return $this->hasMany(EraChecklistItem::class, 'checklist_template_id');
    }
}
