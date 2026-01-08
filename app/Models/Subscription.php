<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_type',
        'transaction_id',
        'amount',
        'starts_at',
        'expires_at',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
