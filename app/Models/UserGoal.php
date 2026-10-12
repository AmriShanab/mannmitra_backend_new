<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserGoal extends Model
{
    protected $fillable = [
        'user_id',
        'exercise_run_id',
        'core_values',
        'focus_value',
        'goal_text',
        'confidence',
        'status',
        'progress',
        'due_date',
        'checked_in_at',
    ];

    // The goal is personal, so it is encrypted at rest.
    protected $casts = [
        'goal_text' => 'encrypted',
        'core_values' => 'array',
        'due_date' => 'date',
        'checked_in_at' => 'datetime',
    ];
}
