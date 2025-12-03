<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrisisAlert extends Model
{
    protected $fillable = [
        'session_id',
        'trigger_keyword',
        'severity',
        'status'
    ];

    public function session()
    {
        return $this->belongsTo(Session::class);
    }
}
