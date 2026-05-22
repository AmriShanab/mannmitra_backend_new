<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tickets extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'listener_id',
        'subject',
        'status',
        'payment_verified',
        'razorpay_order_id',    // Add this
        'razorpay_payment_id'   // Add this
    ];

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function listener()
    {
        return $this->belongsTo(User::class, 'listener_id');
    }

    public function payment()
    {
        return $this->hasOne(Payments::class);
    }
}
