<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListerMessages extends Model
{
    use HasFactory;

    protected $table = 'lister_messages';

    protected $fillable = [
        'ticket_id',
        'sender_id',
        'message',
    ];
}
