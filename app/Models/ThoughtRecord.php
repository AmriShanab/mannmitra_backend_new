<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThoughtRecord extends Model
{
    protected $fillable = [
        'user_id',
        'exercise_run_id',
        'situation',
        'automatic_thought',
        'distortion',
        'intensity_before',
        'evidence_against',
        'balanced_thought',
        'intensity_after',
    ];

    // Personal reflections are encrypted at rest.
    protected $casts = [
        'situation' => 'encrypted',
        'automatic_thought' => 'encrypted',
        'evidence_against' => 'encrypted',
        'balanced_thought' => 'encrypted',
    ];
}
