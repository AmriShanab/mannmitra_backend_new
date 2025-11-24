<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoodEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'primary_mood',
        'secondary_tags',
        'note'
    ];

    protected function casts():array
    {
        return [
            'secondary_tags' => 'array', 
            'created_at' => 'datetime',
        ];
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
