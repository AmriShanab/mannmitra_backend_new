<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExerciseRun extends Model
{
    protected $fillable = [
        'user_id',
        'activity_id',
        'exercise_credit_id',
        'status',
        'step',
        'state',
        'free_pass',
        'completed_at',
    ];

    protected $casts = [
        'free_pass' => 'boolean',
        'state' => 'array',
        'completed_at' => 'datetime',
    ];
}
