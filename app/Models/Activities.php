<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activities extends Model
{
    protected $fillable = [
        'title',
        'description',
        'duration',
        'icon_name',
        'color_hex',
    ];
}
