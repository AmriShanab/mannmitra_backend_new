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
        'razorpay_order_id',
        'razorpay_payment_id',
        'reminder_sent_at',
    ];

    /**
     * Confirmed appointments that start within the next 30 minutes and have not been reminded.
     * scheduled_at is stored as Indian wall-clock time, so "now" is taken in Asia/Kolkata.
     */
    public function scopeDueForReminder($query, $now = null)
    {
        $now = $now ? \Carbon\Carbon::parse($now, 'Asia/Kolkata') : \Carbon\Carbon::now('Asia/Kolkata');

        return $query->where('status', 'confirmed')
            ->whereNull('reminder_sent_at')
            ->where('scheduled_at', '>', $now->format('Y-m-d H:i:s'))
            ->where('scheduled_at', '<=', $now->copy()->addMinutes(30)->format('Y-m-d H:i:s'));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function psychiatrist()
    {
        return $this->belongsTo(User::class, 'psychiatrist_id');
    }
}
