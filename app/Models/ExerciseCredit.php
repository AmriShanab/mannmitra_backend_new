<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExerciseCredit extends Model
{
    protected $fillable = [
        'user_id',
        'activity_id',
        'razorpay_order_id',
        'transaction_id',
        'amount',
        'sessions_total',
        'sessions_used',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
