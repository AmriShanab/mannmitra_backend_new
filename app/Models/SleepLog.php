<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SleepLog extends Model
{
    protected $fillable = [
        'user_id',
        'exercise_run_id',
        'log_date',
        'hours',
        'quality',
        'checklist',
    ];

    protected $casts = [
        'log_date' => 'date',
        'hours' => 'float',
        'quality' => 'integer',
        'checklist' => 'array',
    ];
}
