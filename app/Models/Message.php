<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;
    protected $fillable = [
        'session_id', 'sender', 'type', 'content', 'audio_path', 'is_crisis'
    ];

    public function session ()
    {
        return $this->belongsTo(Session::class);
    }
}
