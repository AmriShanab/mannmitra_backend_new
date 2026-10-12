<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GratitudeEntry extends Model
{
    protected $fillable = [
        'user_id',
        'exercise_run_id',
        'entry_date',
        'items',
    ];

    // Personal reflections are encrypted at rest (a JSON list of three strings).
    protected $casts = [
        'items' => 'encrypted:array',
        'entry_date' => 'date',
    ];
}
