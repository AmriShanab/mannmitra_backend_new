<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activities extends Model
{
    protected $fillable = [
        'activity_id',
        'title',
        'description',
        'duration',
        'icon_name',
        'color_hex',
        'translations',
    ];

    protected $casts = [
        'translations' => 'array',
    ];
}
