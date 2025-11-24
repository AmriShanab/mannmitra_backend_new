<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Session extends Model
{
    use HasFactory;

    protected $table = 'session';

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'mood_snapshot',
        'summary'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
