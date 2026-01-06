<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tickets extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function listener()
    {
        return $this->belongsTo(User::class, 'listener_id');
    }

    public function payment() {
        return $this->hasOne(Payments::class);
    }
}
