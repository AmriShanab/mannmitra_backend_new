<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
        'appointment_id',
        'user_id',
        'psychiatrist_id',
        'scheduled_at',
        'mode',
        'status',
        'meeting_link',
        'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function psychiatrist()
    {
        return $this->belongsTo(User::class, 'psychiatrist_id');
    }
}
